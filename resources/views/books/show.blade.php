@extends('layouts/app', ['title' => $book->title])

@section('content')
<!-- Include PDF.js library locally -->
<script src="{{ asset('js/pdf.min.js') }}"></script>

<script>
    // Global error listener to capture and show errors on the UI for debugging
    window.addEventListener('error', function(e) {
        const debugEl = document.getElementById('debug-error-message');
        if (debugEl) {
            debugEl.style.display = 'block';
            debugEl.innerText += '\n[JS Error]: ' + e.message + ' (' + e.filename + ':' + e.lineno + ')';
        }
    });

    const initAlpineReader = () => {
        Alpine.data('reader', (config) => {
            // Keep PDF.js instance as a closure variable instead of Alpine reactive property.
            // This prevents Alpine from wrapping it in a Proxy, which crashes on ES private properties (#) in pdf.js.
            let pdfDoc = null;
            let panFrame = null;
            let panContainer = null;
            let panLastX = 0;
            let panLastY = 0;

            const savedPage = localStorage.getItem(`book_bookmark_${config.bookId}`);
            const initialPage = savedPage ? Math.min(parseInt(savedPage, 10), 10000) : 0;

            return {
                currentPage: initialPage,
                totalPages: 0,
                isFullscreen: false,
                orientation: 'landscape',
                loading: true,
                loadingProgress: 0,
                renderedPages: new Set(),
                renderTasks: {},
                
                // Dynamic Aspect Ratio & Size Sizing variables
                pdfAspectRatio: 0.75, // default fallback 3:4
                resizeTimeout: null,
                resizeHandler: null,
                
                // Zoom & Pan variables
                zoom: 1.0,
                panStartX: 0,
                panStartY: 0,
                scrollStartX: 0,
                scrollStartY: 0,
                isPanning: false,
                isPinching: false,
                initialPinchDistance: 0,
                initialPinchZoom: 1.0,

                // Right-Click Context Menu State
                showContextMenu: false,
                contextMenuX: 0,
                contextMenuY: 0,

                // Custom CSS Flipbook state variables
                pageWidth: 450,
                pageHeight: 600,
                bookWidth: 900,
                bookHeight: 600,
                overrideZIndexSheet: null,
                overrideTimeout: null,

                loadingStatus: 'Downloading document...',
                get loadingPercent() {
                    switch (this.loadingStatus) {
                        case 'Downloading document...': return 25;
                        case 'Analyzing pages...': return 50;
                        case 'Rendering cover page...': return 75;
                        case 'Rendering bookmarked page...': return 75;
                        case 'Opening book...': return 100;
                        default: return 10;
                    }
                },

                handleWheel(e) {
                    if (!this.isFullscreen) return;
                    e.preventDefault();
                    if (e.deltaY < 0) {
                        this.zoomIn();
                    } else {
                        this.zoomOut();
                    }
                },

                handleContextMenu(e) {
                    if (!this.isFullscreen) return;
                    e.preventDefault();
                    
                    const rect = this.$refs.readerContainer.getBoundingClientRect();
                    let x = e.clientX - rect.left;
                    let y = e.clientY - rect.top;
                    
                    const menuW = 192;
                    const menuH = 175;
                    
                    if (x + menuW > rect.width) {
                        x = rect.width - menuW - 10;
                    }
                    if (y + menuH > rect.height) {
                        y = rect.height - menuH - 10;
                    }
                    
                    this.contextMenuX = Math.max(10, x);
                    this.contextMenuY = Math.max(10, y);
                    this.showContextMenu = true;
                    
                    this.$nextTick(() => {
                        if (window.lucide) {
                            window.lucide.createIcons();
                        }
                    });
                },

                zoomIn() {
                    const previousZoom = this.zoom;
                    const scrollCenter = this.getScrollCenter();
                    this.zoom = Math.min(2.5, this.zoom + 0.25);
                    this.updateSheetStyles();
                    this.restoreZoomScroll(previousZoom, scrollCenter);
                },
                zoomOut() {
                    const previousZoom = this.zoom;
                    const scrollCenter = this.getScrollCenter();
                    this.zoom = Math.max(1.0, this.zoom - 0.25);
                    if (this.zoom <= 1) {
                        this.resetZoom();
                    } else {
                        this.updateSheetStyles();
                        this.restoreZoomScroll(previousZoom, scrollCenter);
                    }
                },
                resetZoom() {
                    this.zoom = 1.0;
                    const container = document.getElementById('book-zoom-container');
                    if (container) {
                        container.scrollLeft = 0;
                        container.scrollTop = 0;
                    }
                    this.updateSheetStyles();
                },
                getScrollCenter() {
                    const container = document.getElementById('book-zoom-container');
                    if (!container || this.zoom <= 1) {
                        return { x: 0.5, y: 0.5 };
                    }

                    return {
                        x: (container.scrollLeft + (container.clientWidth / 2)) / Math.max(container.scrollWidth, 1),
                        y: (container.scrollTop + (container.clientHeight / 2)) / Math.max(container.scrollHeight, 1)
                    };
                },
                restoreZoomScroll(previousZoom, scrollCenter) {
                    this.$nextTick(() => {
                        requestAnimationFrame(() => {
                            const container = document.getElementById('book-zoom-container');
                            if (!container || this.zoom <= 1) return;

                            const maxLeft = Math.max(0, container.scrollWidth - container.clientWidth);
                            const maxTop = Math.max(0, container.scrollHeight - container.clientHeight);
                            const targetX = previousZoom <= 1
                                ? maxLeft / 2
                                : (scrollCenter.x * container.scrollWidth) - (container.clientWidth / 2);
                            const targetY = previousZoom <= 1
                                ? maxTop / 2
                                : (scrollCenter.y * container.scrollHeight) - (container.clientHeight / 2);

                            container.scrollLeft = Math.min(maxLeft, Math.max(0, targetX));
                            container.scrollTop = Math.min(maxTop, Math.max(0, targetY));
                        });
                    });
                },

                startPan(e) {
                    if (e.touches && e.touches.length === 2) {
                        this.isPinching = true;
                        this.initialPinchDistance = Math.hypot(
                            e.touches[0].clientX - e.touches[1].clientX,
                            e.touches[0].clientY - e.touches[1].clientY
                        );
                        this.initialPinchZoom = this.zoom;
                        return;
                    }

                    if (this.zoom <= 1) return;
                    this.isPanning = true;
                    
                    const touch = e.touches ? e.touches[0] : e;
                    
                    e.stopPropagation();
                    if (!e.touches) {
                        e.preventDefault();
                    }
                    
                    panContainer = document.getElementById('book-zoom-container');
                    if (!panContainer) {
                        this.isPanning = false;
                        return;
                    }

                    this.panStartX = touch.clientX;
                    this.panStartY = touch.clientY;
                    panLastX = touch.clientX;
                    panLastY = touch.clientY;
                    this.scrollStartX = panContainer.scrollLeft;
                    this.scrollStartY = panContainer.scrollTop;
                },
                
                pan(e) {
                    if (e.touches && e.touches.length === 2 && this.isPinching) {
                        e.preventDefault();
                        const currentDistance = Math.hypot(
                            e.touches[0].clientX - e.touches[1].clientX,
                            e.touches[0].clientY - e.touches[1].clientY
                        );
                        const factor = currentDistance / this.initialPinchDistance;
                        let targetZoom = Math.min(2.5, Math.max(1.0, this.initialPinchZoom * factor));
                        
                        this.zoom = parseFloat(targetZoom.toFixed(2));
                        this.updateSheetStyles();
                        return;
                    }

                    if (!this.isPanning || this.zoom <= 1) return;
                    
                    if (e.cancelable) {
                        e.preventDefault();
                    }

                    const touch = e.touches ? e.touches[0] : e;
                    panLastX = touch.clientX;
                    panLastY = touch.clientY;
                    
                    if (panFrame) return;

                    panFrame = requestAnimationFrame(() => {
                        panFrame = null;
                        if (!this.isPanning || !panContainer) return;

                        const dx = panLastX - this.panStartX;
                        const dy = panLastY - this.panStartY;

                        // Standard drag panning (grab and pull the page content)
                        panContainer.scrollLeft = this.scrollStartX - dx;
                        panContainer.scrollTop = this.scrollStartY - dy;
                    });
                },
                
                endPan() {
                    this.isPinching = false;
                    if (panFrame) {
                        cancelAnimationFrame(panFrame);
                        panFrame = null;
                    }
                    if (panContainer) {
                        const dx = panLastX - this.panStartX;
                        const dy = panLastY - this.panStartY;
                        panContainer.scrollLeft = this.scrollStartX - dx;
                        panContainer.scrollTop = this.scrollStartY - dy;
                    }
                    this.isPanning = false;
                    panContainer = null;
                },
                
                calculateBookSize(pdfAspectRatio) {
                    const container = document.getElementById('book-zoom-container');
                    let containerWidth = window.innerWidth * 0.9;
                    let containerHeight = window.innerHeight * 0.75;
                    
                    if (container && container.clientWidth > 0 && container.clientHeight > 0) {
                        containerWidth = container.clientWidth;
                        containerHeight = container.clientHeight;
                    }
                    
                    const paddingX = 64;
                    const paddingY = 80;
                    
                    const availableW = Math.max(200, containerWidth - paddingX);
                    const availableH = Math.max(250, containerHeight - paddingY);
                    
                    let pageW, pageH;
                    const isLandscape = availableW > availableH;
                    
                    if (isLandscape) {
                        const bookRatio = 2 * pdfAspectRatio;
                        if (availableW / availableH > bookRatio) {
                            pageH = availableH;
                            pageW = pageH * pdfAspectRatio;
                        } else {
                            pageW = availableW / 2;
                            pageH = pageW / pdfAspectRatio;
                        }
                    } else {
                        const bookRatio = pdfAspectRatio;
                        if (availableW / availableH > bookRatio) {
                            pageH = availableH;
                            pageW = pageH * pdfAspectRatio;
                        } else {
                            pageW = availableW;
                            pageH = pageW / pdfAspectRatio;
                        }
                    }
                    
                    pageW = Math.max(150, Math.min(1000, pageW));
                    pageH = Math.max(200, Math.min(1400, pageH));
                    
                    return {
                        width: Math.floor(pageW),
                        height: Math.floor(pageH)
                    };
                },

                registerResizeListener() {
                    if (this.resizeHandler) {
                        window.removeEventListener('resize', this.resizeHandler);
                    }
                    this.resizeHandler = () => {
                        clearTimeout(this.resizeTimeout);
                        this.resizeTimeout = setTimeout(() => {
                            this.resizeReader();
                        }, 250);
                    };
                    window.addEventListener('resize', this.resizeHandler);
                },

                resizeReader() {
                    if (this.loading || this.totalPages === 0) return;

                    // Cancel all active tasks on resize
                    Object.keys(this.renderTasks).forEach((pageStr) => {
                        const pageNum = parseInt(pageStr, 10);
                        const task = this.renderTasks[pageNum];
                        if (task && typeof task.cancel === 'function') {
                            try {
                                task.cancel();
                            } catch (e) {}
                        }
                    });
                    this.renderTasks = {};
                    this.renderedPages.clear();
                    
                    const dims = this.calculateBookSize(this.pdfAspectRatio);
                    this.pageWidth = dims.width;
                    this.pageHeight = dims.height;

                    const container = document.getElementById('book-zoom-container');
                    const containerW = (container && container.clientWidth > 0) ? container.clientWidth : window.innerWidth * 0.9;
                    const containerH = (container && container.clientHeight > 0) ? container.clientHeight : window.innerHeight * 0.75;

                    if (containerW > containerH) {
                        this.orientation = 'landscape';
                        this.bookWidth = this.pageWidth * 2;
                        this.bookHeight = this.pageHeight;
                    } else {
                        this.orientation = 'portrait';
                        this.bookWidth = this.pageWidth;
                        this.bookHeight = this.pageHeight;
                    }
                    
                    const bookEl = document.getElementById('book');
                    if (bookEl) {
                        bookEl.style.width = `${this.bookWidth}px`;
                        bookEl.style.height = `${this.bookHeight}px`;
                    }
                    
                    this.updateSheetStyles();
                    this.renderLazyPages(this.currentPage);
                },

                initReader() {
                    const streamUrl = config.streamUrl;
                    this.loadingStatus = 'Downloading document...';
                    
                    pdfjsLib.GlobalWorkerOptions.workerSrc = config.workerUrl;
                    
                    pdfjsLib.getDocument(streamUrl).promise.then((pdf) => {
                        pdfDoc = pdf;
                        const pdfPageCount = pdf.numPages;
                        this.totalPages = pdfPageCount;
                        this.loadingStatus = 'Analyzing pages...';
                        
                        pdf.getPage(1).then((page) => {
                            const viewport = page.getViewport({ scale: 1.0 });
                            this.pdfAspectRatio = viewport.width / viewport.height;
                            console.log('PDF Original Aspect Ratio:', this.pdfAspectRatio);
                            
                            this.loadingStatus = this.currentPage > 0 ? 'Rendering bookmarked page...' : 'Rendering cover page...';
                            
                            const dims = this.calculateBookSize(this.pdfAspectRatio);
                            this.pageWidth = dims.width;
                            this.pageHeight = dims.height;

                            const containerW = window.innerWidth * 0.9;
                            const containerH = window.innerHeight * 0.75;

                            if (containerW > containerH) {
                                this.orientation = 'landscape';
                                this.bookWidth = this.pageWidth * 2;
                                this.bookHeight = this.pageHeight;
                            } else {
                                this.orientation = 'portrait';
                                this.bookWidth = this.pageWidth;
                                this.bookHeight = this.pageHeight;
                            }

                            const bookEl = document.getElementById('book');
                            if (bookEl) {
                                bookEl.innerHTML = '';
                                this.buildFlipbookDOM(this.totalPages);
                                bookEl.style.width = `${this.bookWidth}px`;
                                bookEl.style.height = `${this.bookHeight}px`;
                                this.updateSheetStyles();
                            }

                            // Calculate initial pages to render
                            const initialPages = [this.currentPage + 1];
                            if (this.orientation === 'landscape' && this.currentPage > 0 && (this.currentPage + 2) <= this.totalPages) {
                                initialPages.push(this.currentPage + 2);
                            }

                            Promise.all(initialPages.map(pageNo => this.renderPageCanvasPromise(pageNo))).then(() => {
                                this.loadingStatus = 'Opening book...';
                                this.loading = false;
                                
                                this.$nextTick(() => {
                                    this.initFlipbook();
                                    this.registerResizeListener();
                                });
                            }).catch((err) => {
                                console.error('Failed to render initial pages pre-emptively, opening anyway:', err);
                                this.loading = false;
                                this.$nextTick(() => {
                                    this.initFlipbook();
                                    this.registerResizeListener();
                                });
                            });
                        }).catch((err) => {
                            console.error('Failed to get page 1 for aspect ratio, using 3:4 fallback:', err);
                            this.pdfAspectRatio = 0.75;
                            this.loading = false;
                            this.$nextTick(() => {
                                this.initFlipbook();
                                this.registerResizeListener();
                            });
                        });
                    }).catch((err) => {
                        console.error('Failed to load secure PDF:', err);
                        
                        const debugEl = document.getElementById('debug-error-message');
                        if (debugEl) {
                            debugEl.style.display = 'block';
                            debugEl.innerText += '\n[PDF Load Error]: ' + err.message + '\nStack: ' + (err.stack || '');
                        }
                        
                        alert('Unable to load ebook file securely. Please try again.');
                        this.loading = false;
                    });
                },

                buildFlipbookDOM(pdfPageCount) {
                    const bookEl = document.getElementById('book');
                    if (!bookEl) return;
                    bookEl.innerHTML = '';
                    
                    const numSheets = Math.ceil(pdfPageCount / 2);
                    
                    for (let s = 1; s <= numSheets; s++) {
                        const sheetEl = document.createElement('div');
                        sheetEl.id = `sheet-${s}`;
                        sheetEl.className = 'flip-sheet';
                        
                        const pFront = 2 * s - 1;
                        const pBack = 2 * s;
                        
                        let frontHTML = '';
                        if (pFront <= pdfPageCount) {
                            frontHTML = `
                                <div class="front-side">
                                    <div class="w-full h-full flex flex-col justify-between bg-white">
                                        <div class="flex-1 relative bg-white flex items-center justify-center p-2">
                                            <canvas id="canvas-page-${pFront}" class="max-w-full max-h-full object-contain pointer-events-none"></canvas>
                                            <div id="spinner-page-${pFront}" class="absolute inset-0 flex items-center justify-center bg-white z-10">
                                                <div class="animate-spin rounded-full h-6 w-6 border-2 border-emerald-500 border-t-transparent"></div>
                                            </div>
                                        </div>
                                        <div class="py-2.5 px-6 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400 font-bold select-none">
                                            <span>AMIS e-Book</span>
                                            <span>Page ${pFront} of ${pdfPageCount}</span>
                                        </div>
                                    </div>
                                </div>
                             `;
                        } else {
                            frontHTML = `<div class="front-side"><div class="w-full h-full bg-slate-50"></div></div>`;
                        }
                        
                        let backHTML = '';
                        if (pBack <= pdfPageCount) {
                            backHTML = `
                                <div class="back-side">
                                    <div class="w-full h-full flex flex-col justify-between bg-white">
                                        <div class="flex-1 relative bg-white flex items-center justify-center p-2">
                                            <canvas id="canvas-page-${pBack}" class="max-w-full max-h-full object-contain pointer-events-none"></canvas>
                                            <div id="spinner-page-${pBack}" class="absolute inset-0 flex items-center justify-center bg-white z-10">
                                                <div class="animate-spin rounded-full h-6 w-6 border-2 border-emerald-500 border-t-transparent"></div>
                                            </div>
                                        </div>
                                        <div class="py-2.5 px-6 bg-slate-50 border-t border-slate-100 flex items-center justify-between text-[10px] text-slate-400 font-bold select-none">
                                            <span>AMIS e-Book</span>
                                            <span>Page ${pBack} of ${pdfPageCount}</span>
                                        </div>
                                    </div>
                                </div>
                            `;
                        } else {
                            backHTML = `<div class="back-side"><div class="w-full h-full bg-slate-50"></div></div>`;
                        }
                        
                        sheetEl.innerHTML = frontHTML + backHTML;
                        bookEl.appendChild(sheetEl);
                    }
                },

                initFlipbook() {
                    if (this.loading || this.totalPages === 0) return;
                    
                    const bookEl = document.getElementById('book');
                    if (!bookEl) return;

                    const preRendered = new Set(this.renderedPages);

                    if (bookEl.children.length === 0) {
                        bookEl.innerHTML = '';
                        this.buildFlipbookDOM(this.totalPages);
                    }

                    this.renderedPages.clear();
                    preRendered.forEach(p => this.renderedPages.add(p));

                    const dims = this.calculateBookSize(this.pdfAspectRatio);
                    this.pageWidth = dims.width;
                    this.pageHeight = dims.height;

                    const container = document.getElementById('book-zoom-container');
                    const containerW = (container && container.clientWidth > 0) ? container.clientWidth : window.innerWidth * 0.9;
                    const containerH = (container && container.clientHeight > 0) ? container.clientHeight : window.innerHeight * 0.75;

                    if (containerW > containerH) {
                        this.orientation = 'landscape';
                        this.bookWidth = this.pageWidth * 2;
                        this.bookHeight = this.pageHeight;
                    } else {
                        this.orientation = 'portrait';
                        this.bookWidth = this.pageWidth;
                        this.bookHeight = this.pageHeight;
                    }

                    bookEl.style.width = `${this.bookWidth}px`;
                    bookEl.style.height = `${this.bookHeight}px`;

                    this.updateSheetStyles();
                    
                    bookEl.classList.remove('opacity-0');

                    this.renderLazyPages(this.currentPage);

                    if (window.lucide) {
                        window.lucide.createIcons();
                    }
                },

                updateSheetStyles() {
                    const numSheets = Math.ceil(this.totalPages / 2);
                    
                    const bookEl = document.getElementById('book');
                    if (bookEl) {
                        bookEl.style.transform = this.getBookTransform();
                    }

                    const currentSheet = Math.floor(this.currentPage / 2) + 1;
                    const visibleRange = 2; // Keep current, previous, and next spreads visible

                    for (let s = 1; s <= numSheets; s++) {
                        const sheetEl = document.getElementById(`sheet-${s}`);
                        if (!sheetEl) continue;

                        const isVisible = Math.abs(s - currentSheet) <= visibleRange;

                        if (!isVisible) {
                            sheetEl.style.display = 'none';
                            continue;
                        }

                        sheetEl.style.width = `${this.pageWidth}px`;
                        sheetEl.style.height = `${this.pageHeight}px`;

                        const frontSide = sheetEl.querySelector('.front-side');
                        const backSide = sheetEl.querySelector('.back-side');

                        if (this.orientation === 'landscape') {
                            sheetEl.style.position = 'absolute';
                            sheetEl.style.top = '0';
                            sheetEl.style.left = '50%';
                            sheetEl.style.transformOrigin = 'left center';
                            sheetEl.style.display = 'block';
                            sheetEl.style.opacity = '1';
                            sheetEl.style.pointerEvents = 'auto';

                            if (frontSide) {
                                frontSide.style.display = 'block';
                                frontSide.style.opacity = '1';
                            }
                            if (backSide) {
                                backSide.style.display = 'block';
                                backSide.style.opacity = '1';
                                backSide.style.transform = 'rotateY(180deg)';
                            }

                            const isFlipped = this.currentPage >= (2 * s - 1);
                            
                            if (isFlipped) {
                                sheetEl.style.transform = 'rotateY(-180deg)';
                            } else {
                                sheetEl.style.transform = 'rotateY(0deg)';
                            }

                            if (this.overrideZIndexSheet === s) {
                                sheetEl.style.zIndex = '99';
                            } else {
                                if (isFlipped) {
                                    sheetEl.style.zIndex = `${s}`;
                                } else {
                                    sheetEl.style.zIndex = `${numSheets - s + 1}`;
                                }
                            }
                        } else {
                            sheetEl.style.position = 'absolute';
                            sheetEl.style.top = '0';
                            sheetEl.style.left = '0';
                            sheetEl.style.transformOrigin = 'center center';
                            sheetEl.style.transform = 'none';

                            const isFrontActive = (this.currentPage === 2 * s - 2);
                            const isBackActive = (this.currentPage === 2 * s - 1);
                            const isActive = isFrontActive || isBackActive;

                            if (isActive) {
                                sheetEl.style.display = 'block';
                                sheetEl.style.opacity = '1';
                                sheetEl.style.zIndex = '10';
                                sheetEl.style.pointerEvents = 'auto';

                                if (frontSide) {
                                    frontSide.style.display = isFrontActive ? 'block' : 'none';
                                    frontSide.style.opacity = isFrontActive ? '1' : '0';
                                }
                                if (backSide) {
                                    backSide.style.display = isBackActive ? 'block' : 'none';
                                    backSide.style.opacity = isBackActive ? '1' : '0';
                                    backSide.style.transform = 'none';
                                }
                            } else {
                                sheetEl.style.display = 'none';
                                sheetEl.style.opacity = '0';
                                sheetEl.style.zIndex = '1';
                                sheetEl.style.pointerEvents = 'none';
                            }
                        }
                    }
                },

                getBookTransform() {
                    if (this.orientation === 'portrait') {
                        return `scale(${this.zoom})`;
                    }
                    let tx = 0;
                    // Only translate cover pages to center them when not zoomed.
                    // When zoomed, translation is disabled to prevent scroll boundaries from breaking.
                    if (this.zoom === 1.0) {
                        if (this.currentPage === 0) {
                            tx = -25;
                        } else if (this.currentPage === this.totalPages - 1 && this.totalPages % 2 === 0) {
                            tx = 25;
                        }
                    }
                    if (tx === 0) {
                        return `scale(${this.zoom})`;
                    }
                    return `scale(${this.zoom}) translateX(${tx}%)`;
                },

                triggerFlip(targetPage) {
                    if (this.orientation !== 'landscape') {
                        this.currentPage = targetPage;
                        localStorage.setItem(`book_bookmark_${config.bookId}`, this.currentPage);
                        this.renderLazyPages(this.currentPage);
                        this.updateSheetStyles();
                        return;
                    }

                    let flippingSheetIndex = 0;
                    if (targetPage > this.currentPage) {
                        flippingSheetIndex = Math.floor(targetPage / 2) + 1;
                    } else {
                        flippingSheetIndex = Math.floor(this.currentPage / 2) + 1;
                    }

                    this.overrideZIndexSheet = flippingSheetIndex;
                    this.currentPage = targetPage;
                    localStorage.setItem(`book_bookmark_${config.bookId}`, this.currentPage);
                    
                    this.renderLazyPages(this.currentPage);
                    this.updateSheetStyles();

                    clearTimeout(this.overrideTimeout);
                    this.overrideTimeout = setTimeout(() => {
                        this.overrideZIndexSheet = null;
                        this.updateSheetStyles();
                    }, 600);
                },

                handleBookClick(e) {
                    if (this.zoom > 1.0) return;

                    const bookEl = document.getElementById('book');
                    if (!bookEl) return;

                    const rect = bookEl.getBoundingClientRect();
                    const clickX = e.clientX - rect.left;
                    const bookW = rect.width;

                    if (clickX > bookW / 2) {
                        this.nextPage();
                    } else {
                        this.prevPage();
                    }
                },

                cancelInvisibleRenderTasks(visiblePages) {
                    Object.keys(this.renderTasks).forEach((pageStr) => {
                        const pageNum = parseInt(pageStr, 10);
                        if (!visiblePages.has(pageNum)) {
                            const task = this.renderTasks[pageNum];
                            if (task && typeof task.cancel === 'function') {
                                try {
                                    task.cancel();
                                    console.log(`Cancelled render task for page ${pageNum}`);
                                } catch (e) {
                                    console.error(`Error cancelling task for page ${pageNum}:`, e);
                                }
                            }
                            delete this.renderTasks[pageNum];
                            this.renderedPages.delete(pageNum);
                        }
                    });
                },

                renderLazyPages(flipbookPageIndex) {
                    const pagesToLoad = new Set();
                    
                    pagesToLoad.add(flipbookPageIndex + 1);
                    
                    if (this.orientation === 'landscape') {
                        if (flipbookPageIndex > 0) {
                            pagesToLoad.add(flipbookPageIndex + 2);
                        }
                        
                        pagesToLoad.add(flipbookPageIndex + 3);
                        pagesToLoad.add(flipbookPageIndex + 4);
                        
                        if (flipbookPageIndex > 1) {
                            pagesToLoad.add(flipbookPageIndex);
                            pagesToLoad.add(flipbookPageIndex - 1);
                        }
                    } else {
                        pagesToLoad.add(flipbookPageIndex + 2);
                        if (flipbookPageIndex > 0) {
                            pagesToLoad.add(flipbookPageIndex);
                        }
                    }
                    
                    this.cancelInvisibleRenderTasks(pagesToLoad);

                    Array.from(pagesToLoad)
                        .filter(num => num >= 1 && num <= this.totalPages)
                        .forEach(num => this.renderPageCanvas(num));
                },

                renderPageCanvasPromise(pageNumber) {
                    if (this.renderedPages.has(pageNumber)) {
                        return Promise.resolve();
                    }
                    this.renderedPages.add(pageNumber);

                    if (this.renderTasks[pageNumber]) {
                        return Promise.resolve();
                    }

                    return pdfDoc.getPage(pageNumber).then((page) => {
                        if (!this.renderedPages.has(pageNumber)) {
                            return;
                        }

                        const canvas = document.getElementById('canvas-page-' + pageNumber);
                        if (!canvas) {
                            this.renderedPages.delete(pageNumber);
                            return;
                        }

                        const context = canvas.getContext('2d');
                        const viewport = page.getViewport({ scale: 2.0 });
                        canvas.height = viewport.height;
                        canvas.width = viewport.width;

                        const renderContext = {
                            canvasContext: context,
                            viewport: viewport
                        };

                        const renderTask = page.render(renderContext);
                        this.renderTasks[pageNumber] = renderTask;

                        const spinner = document.getElementById('spinner-page-' + pageNumber);
                        if (spinner) {
                            spinner.style.display = 'flex';
                        }

                        return renderTask.promise.then(() => {
                            delete this.renderTasks[pageNumber];
                            if (spinner) {
                                spinner.style.display = 'none';
                            }
                        }).catch((err) => {
                            delete this.renderTasks[pageNumber];
                            this.renderedPages.delete(pageNumber);
                            if (spinner) {
                                spinner.style.display = 'flex';
                            }
                            if (err.name === 'RenderingCancelledException' || err.message === 'Rendering cancelled, closed or replaced.') {
                                console.log('Rendering cancelled for page ' + pageNumber);
                            } else {
                                throw err;
                            }
                        });
                    }).catch((err) => {
                        if (err.name === 'RenderingCancelledException' || err.message === 'Rendering cancelled, closed or replaced.') {
                            return;
                        }
                        console.error('Error rendering page:', pageNumber, err);
                        this.renderedPages.delete(pageNumber);
                        throw err;
                    });
                },

                renderPageCanvas(pageNumber) {
                    this.renderPageCanvasPromise(pageNumber).catch((err) => {
                        console.error('Lazy render error:', pageNumber, err);
                    });
                },

                nextPage() {
                    let nextVal = this.currentPage;
                    if (this.orientation === 'landscape') {
                        if (this.currentPage === 0) {
                            nextVal = 1;
                        } else {
                            nextVal = Math.min(this.totalPages - 1, this.currentPage + 2);
                        }
                    } else {
                        nextVal = Math.min(this.totalPages - 1, this.currentPage + 1);
                    }
                    if (nextVal !== this.currentPage) {
                        this.triggerFlip(nextVal);
                    }
                },
                prevPage() {
                    let prevVal = this.currentPage;
                    if (this.orientation === 'landscape') {
                        if (this.currentPage <= 2) {
                            prevVal = 0;
                        } else {
                            prevVal = Math.max(0, this.currentPage - 2);
                        }
                    } else {
                        prevVal = Math.max(0, this.currentPage - 1);
                    }
                    if (prevVal !== this.currentPage) {
                        this.triggerFlip(prevVal);
                    }
                },
                goToPage(num) {
                    if (num >= 0 && num < this.totalPages && num !== this.currentPage) {
                        let target = num;
                        if (this.orientation === 'landscape' && target > 0) {
                            if (target === this.totalPages - 1 && this.totalPages % 2 !== 0) {
                                // Keep last page in odd books
                            } else if (target % 2 === 0) {
                                target = target - 1;
                            }
                        }
                        this.triggerFlip(target);
                    }
                },
                toggleFullscreen() {
                    const el = this.$refs.readerContainer;
                    if (!document.fullscreenElement) {
                        if (el.requestFullscreen) {
                            el.requestFullscreen().catch(() => {
                                this.isFullscreen = true;
                                this.$nextTick(() => window.dispatchEvent(new Event('resize')));
                            });
                        } else if (el.webkitRequestFullscreen) {
                            el.webkitRequestFullscreen();
                        } else {
                            this.isFullscreen = true;
                            this.$nextTick(() => window.dispatchEvent(new Event('resize')));
                        }
                    } else {
                        if (document.exitFullscreen) {
                            document.exitFullscreen();
                        } else if (document.webkitExitFullscreen) {
                            document.webkitExitFullscreen();
                        }
                        this.isFullscreen = false;
                        this.$nextTick(() => window.dispatchEvent(new Event('resize')));
                    }
                }
            };
        });
    };

    if (window.Alpine) {
        initAlpineReader();
    } else {
        document.addEventListener('alpine:init', initAlpineReader);
    }
</script>

<div class="space-y-6" x-data="reader({
    bookId: {{ Js::from($book->id) }},
    streamUrl: {{ Js::from($streamUrl) }},
    title: {{ Js::from($book->title) }},
    description: {{ Js::from($book->description ?: 'Welcome to the digital edition of this course textbook. Use the navigation buttons or the keyboard left/right arrow keys to flip the pages.') }},
    workerUrl: '{{ asset('js/pdf.worker.min.js') }}'
})" x-init="initReader()" @keydown.right.window="nextPage()" @keydown.left.window="prevPage()" @fullscreenchange.window="isFullscreen = !!document.fullscreenElement; if (!isFullscreen) { resetZoom(); } $nextTick(() => window.dispatchEvent(new Event('resize')))">

    <!-- Debug Error Box -->
    <div id="debug-error-message" style="display: none; background-color: #fee2e2; border: 1px solid #f87171; color: #991b1b; padding: 16px; border-radius: 12px; font-family: monospace; font-size: 13px; white-space: pre-wrap; margin-bottom: 16px; z-index: 9999;">
        <strong>Diagnostic Log:</strong>
    </div>

    <header class="ebook-page-header">
        <div>
            <p class="ebook-eyebrow">Reader</p>
            <h1 class="ebook-title">{{ $book->title }}</h1>
            @php
                $bookGrade = strtolower(trim($book->grade_level ?? ''));
                $bookGrade = preg_replace('/\s+/', ' ', $bookGrade);
                $displayGrade = $bookGrade === 'kindergarten'
                    ? 'KINDER 1 / KINDER 2'
                    : $book->grade_level;

                $bookMeta = collect([$displayGrade])
                    ->filter(fn ($value) => filled($value))
                    ->implode(' · ');
            @endphp
            @if($bookMeta)
                <p class="ebook-subtitle">{{ $bookMeta }}</p>
            @endif
        </div>
        <div class="ebook-actions">
            <button @click="toggleFullscreen()" class="ebook-btn ebook-btn-muted">
                <span class="inline-flex items-center gap-1.5">
                    <span x-show="!isFullscreen" class="inline-flex"><i data-lucide="maximize-2" class="w-3.5 h-3.5"></i></span>
                    <span x-show="isFullscreen" x-cloak class="inline-flex"><i data-lucide="minimize-2" class="w-3.5 h-3.5"></i></span>
                    <span x-text="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'">Fullscreen</span>
                </span>
            </button>
            <a href="{{ route('books.index') }}" class="ebook-btn ebook-btn-muted">
                <i data-lucide="x" class="w-4 h-4"></i>
                Close Reader
            </a>
        </div>
    </header>

    <!-- Spinner Loading State -->
    <div x-show="loading" class="ebook-loading flex flex-col items-center justify-center space-y-4">
        <div class="ebook-spinner"></div>
        <div class="text-center">
            <h2 class="font-extrabold text-slate-800 text-lg mt-4">Loading secure document...</h2>
            <p class="text-sm font-semibold text-slate-400 mt-1" x-text="loadingStatus">Initializing PDF reader and verifying access token.</p>
            
            <!-- Dynamic Progress Bar -->
            <div class="w-64 h-2 bg-slate-100 rounded-full mt-4 mx-auto overflow-hidden relative shadow-inner">
                <div class="h-full bg-emerald-500 rounded-full transition-all duration-300 ease-out"
                     :style="`width: ${loadingPercent}%`"></div>
            </div>
        </div>
    </div>

    <!-- Flipbook Container -->
    <div x-show="!loading" x-ref="readerContainer" class="ebook-reader-stage" :class="isFullscreen ? 'fixed inset-0 z-50 rounded-none' : ''" @contextmenu="handleContextMenu($event)" @click="showContextMenu = false">
        
        <!-- Floating Fullscreen Toolbar -->
        <div class="floating-toolbar" x-show="isFullscreen" x-transition x-cloak>
            <button @click="zoomOut()" class="toolbar-btn" title="Zoom Out" :disabled="zoom <= 1.0">
                <i data-lucide="zoom-out" class="w-4 h-4"></i>
            </button>
            <span class="toolbar-text" x-text="Math.round(zoom * 100) + '%'"></span>
            <button @click="zoomIn()" class="toolbar-btn" title="Zoom In" :disabled="zoom >= 2.5">
                <i data-lucide="zoom-in" class="w-4 h-4"></i>
            </button>
            <button @click="resetZoom()" class="toolbar-btn" title="Reset Zoom" :disabled="zoom === 1.0">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i>
            </button>
            <div class="toolbar-separator"></div>
            <button @click="toggleFullscreen()" class="toolbar-btn toolbar-btn-highlight" :title="isFullscreen ? 'Exit Fullscreen' : 'Fullscreen'">
                <span x-show="!isFullscreen" class="inline-flex"><i data-lucide="maximize-2" class="w-4 h-4"></i></span>
                <span x-show="isFullscreen" x-cloak class="inline-flex"><i data-lucide="minimize-2" class="w-4 h-4"></i></span>
            </button>
        </div>

        <!-- Custom Right-Click Context Menu -->
        <div id="context-menu" 
             x-show="isFullscreen && showContextMenu" 
             x-transition 
             x-cloak
             @click.away="showContextMenu = false"
             class="absolute bg-slate-900/95 border border-slate-700/50 text-white rounded-xl py-2 w-48 z-50 text-sm font-semibold select-none"
             :style="`left: ${contextMenuX}px; top: ${contextMenuY}px;`">
            <button @click="zoomIn(); showContextMenu = false;" class="w-full text-left px-4 py-2 hover:bg-emerald-600 transition flex items-center gap-2" :disabled="zoom >= 2.5">
                <i data-lucide="zoom-in" class="w-4 h-4"></i> Zoom In
            </button>
            <button @click="zoomOut(); showContextMenu = false;" class="w-full text-left px-4 py-2 hover:bg-emerald-600 transition flex items-center gap-2" :disabled="zoom <= 1.0">
                <i data-lucide="zoom-out" class="w-4 h-4"></i> Zoom Out
            </button>
            <button @click="resetZoom(); showContextMenu = false;" class="w-full text-left px-4 py-2 hover:bg-emerald-600 transition flex items-center gap-2" :disabled="zoom === 1.0">
                <i data-lucide="refresh-cw" class="w-4 h-4"></i> Reset Zoom
            </button>
            <div class="border-t border-slate-700/50 my-1"></div>
            <button @click="toggleFullscreen(); showContextMenu = false;" class="w-full text-left px-4 py-2 hover:bg-emerald-600 transition flex items-center gap-2">
                <i data-lucide="minimize-2" class="w-4 h-4"></i> Exit Fullscreen
            </button>
        </div>

        <!-- Scrollable Zoom & Pan Container -->
        <div id="book-zoom-container" class="w-full flex-1 overflow-auto flex relative select-none"
             @mousedown="startPan($event)"
             @mousemove="pan($event)"
             @mouseup="endPan()"
             @mouseleave="endPan()"
             @touchstart="startPan($event)"
             @touchmove="pan($event)"
             @touchend="endPan()"
             @wheel="handleWheel($event)"
             :class="zoom > 1 ? 'is-zoomed cursor-grab active:cursor-grabbing' : 'items-center justify-center'"
             style="max-height: calc(100vh - 180px); min-height: 500px;">
            
            <div id="book-wrapper" class="flex items-center justify-center" 
                 :style="`width: ${bookWidth * zoom}px; height: ${bookHeight * zoom}px; box-sizing: content-box !important;`"
                 style="padding: 4rem;">
                <div id="book" class="opacity-0 mx-auto"
                     @click="handleBookClick($event)"
                     :style="`width: ${bookWidth}px; height: ${bookHeight}px; transform: ${getBookTransform()}; transform-origin: center center;`">
                    <!-- Pages injected dynamically -->
                </div>
            </div>
        </div>

        <!-- Bottom Control Panel -->
        <div class="ebook-reader-controls controls-panel">
            <div class="ebook-reader-control-group">
                <button @click="goToPage(0)" :disabled="currentPage == 0" class="ebook-icon-btn disabled:opacity-40 disabled:cursor-not-allowed" title="First Page">
                    <i data-lucide="chevrons-left" class="w-4 h-4"></i>
                </button>
                <button @click="prevPage()" :disabled="currentPage == 0" class="ebook-btn ebook-btn-primary disabled:opacity-40 disabled:cursor-not-allowed">
                    <i data-lucide="chevron-left" class="w-4 h-4"></i>
                    <span class="btn-text">Prev</span>
                </button>
            </div>

            <div class="ebook-reader-page-label select-none flex items-center justify-center gap-1.5">
                <span class="btn-text">Page</span>
                <input type="number" 
                       :value="currentPage + 1" 
                       @change="let val = parseInt($event.target.value, 10); if(val > 0 && val <= totalPages) { goToPage(val - 1); } else { $event.target.value = currentPage + 1; }"
                       class="w-12 h-7 text-center rounded-lg border border-slate-300 bg-white font-black text-sm text-emerald-700 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 [appearance:textfield] [&::-webkit-outer-spin-button]:appearance-none [&::-webkit-inner-spin-button]:appearance-none"
                >
                <template x-if="orientation === 'landscape' && currentPage > 0 && currentPage + 1 < totalPages">
                    <span class="font-extrabold text-slate-700">
                        - <span x-text="currentPage + 2"></span>
                    </span>
                </template>
                <span class="text-xs text-slate-400 font-bold">/ <span x-text="totalPages"></span></span>
            </div>

            <div class="ebook-reader-control-group">
                <button @click="nextPage()" :disabled="currentPage == totalPages - 1" class="ebook-btn ebook-btn-primary disabled:opacity-40 disabled:cursor-not-allowed">
                    <span class="btn-text">Next</span>
                    <i data-lucide="chevron-right" class="w-4 h-4"></i>
                </button>
                <button @click="goToPage(totalPages - 1)" :disabled="currentPage == totalPages - 1" class="ebook-icon-btn disabled:opacity-40 disabled:cursor-not-allowed" title="Last Page">
                    <i data-lucide="chevrons-right" class="w-4 h-4"></i>
                </button>
            </div>
        </div>

    </div>
</div>

<style>
    .ebook-reader-stage {
        position: relative !important;
        box-shadow: none !important;
    }
    .floating-toolbar {
        position: absolute;
        top: 1.25rem;
        right: 1.25rem;
        z-index: 40;
        display: flex;
        align-items: center;
        gap: 0.25rem;
        background: rgba(15, 23, 42, 0.85);
        backdrop-filter: none;
        -webkit-backdrop-filter: none;
        border: 1px solid rgba(255, 255, 255, 0.15);
        padding: 6px 10px;
        border-radius: 12px;
        box-shadow: none;
        transition: none;
        pointer-events: auto;
    }
    .floating-toolbar.is-fullscreen {
        position: fixed;
        top: 1.25rem;
        right: 1.25rem;
    }
    .toolbar-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 32px;
        height: 32px;
        border-radius: 8px;
        border: none;
        background: transparent;
        color: rgba(255, 255, 255, 0.8);
        cursor: pointer;
        transition: none;
    }
    .toolbar-btn:hover:not(:disabled) {
        background: rgba(255, 255, 255, 0.15);
        color: #fff;
    }
    .toolbar-btn:active:not(:disabled) {
        transform: scale(0.95);
    }
    .toolbar-btn:disabled {
        opacity: 0.3;
        cursor: not-allowed;
    }
    .toolbar-btn-highlight {
        background: #059669;
        color: #fff;
    }
    .toolbar-btn-highlight:hover {
        background: #047857;
    }
    .toolbar-text {
        color: #fff;
        font-size: 12px;
        font-weight: 800;
        min-width: 46px;
        text-align: center;
        user-select: none;
    }
    .toolbar-separator {
        width: 1px;
        height: 18px;
        background: rgba(255, 255, 255, 0.15);
        margin: 0 4px;
    }
    @media (max-width: 640px) {
        .bottom-zoom-controls {
            display: none !important;
        }
        .floating-toolbar {
            top: 0.75rem;
            right: 0.75rem;
            padding: 4px 8px;
        }
        .toolbar-btn {
            width: 28px;
            height: 28px;
        }
        .toolbar-text {
            font-size: 11px;
            min-width: 38px;
        }
    }
    #book-zoom-container {
        width: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: none;
    }
    #book-zoom-container.is-zoomed {
        align-items: flex-start;
        justify-content: flex-start;
        overscroll-behavior: contain;
        scroll-behavior: auto;
        touch-action: none;
    }
    #book-wrapper {
        display: flex;
        flex: 0 0 auto;
        align-items: center;
        justify-content: center;
        transition: none;
        box-sizing: border-box !important;
    }
    #context-menu,
    .controls-panel {
        box-shadow: none !important;
        backdrop-filter: none !important;
        -webkit-backdrop-filter: none !important;
    }
    #book,
    .page,
    .page-side {
        box-shadow: none !important;
        filter: none !important;
    }
    div:fullscreen {
        background-color: #064e3b !important;
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        width: 100vw !important;
        height: 100vh !important;
        padding: 0 !important;
        box-sizing: border-box !important;
        overflow: visible !important;
    }
    div::backdrop {
        background-color: #064e3b !important;
    }
    div:fullscreen #book-zoom-container {
        width: 100% !important;
        height: 100% !important;
        max-height: 100% !important;
        box-sizing: border-box !important;
        overflow-y: auto !important;
        overflow-x: auto !important;
    }
    div:fullscreen #book-wrapper {
        display: flex !important;
        align-items: center !important;
        justify-content: center !important;
        box-sizing: border-box !important;
        padding: 1rem !important;
        min-width: 100% !important;
        min-height: 100% !important;
    }
    div:fullscreen #book {
        position: relative !important;
        margin: 0 auto !important;
        left: 0 !important;
        top: 0 !important;
    }
    div:fullscreen .controls-panel {
        position: fixed !important;
        bottom: 1.5rem !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        width: calc(100% - 3rem) !important;
        max-width: 32rem !important;
        z-index: 50 !important;
        margin-top: 0 !important;
    }
    @media (max-width: 640px) {
        div:fullscreen {
            padding: 1rem !important;
        }
        div:fullscreen #book-zoom-container {
            height: calc(100vh - 130px) !important;
            max-height: calc(100vh - 130px) !important;
        }
        div:fullscreen #book-wrapper {
            padding: 0.5rem !important;
        }
        div:fullscreen .controls-panel {
            bottom: 1.0rem !important;
            width: calc(100% - 2rem) !important;
        }
    }
    #book {
        position: relative;
        perspective: 2000px;
        transform-style: preserve-3d;
        transition: transform 0.6s cubic-bezier(0.25, 1, 0.5, 1);
        max-width: 100%;
        display: block;
    }
    .flip-sheet {
        position: absolute;
        top: 0;
        transform-style: preserve-3d;
        transition: transform 0.6s cubic-bezier(0.25, 1, 0.5, 1);
    }
    .front-side, .back-side {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        backface-visibility: hidden;
        -webkit-backface-visibility: hidden;
        box-shadow: none;
        border-radius: 0 4px 4px 0;
        overflow: hidden;
        background-color: white;
    }
    .back-side {
        transform: rotateY(180deg);
        border-radius: 4px 0 0 4px;
    }
    
    .front-side::after {
        content: none;
    }
    .back-side::after {
        content: none;
    }
</style>
@endsection
