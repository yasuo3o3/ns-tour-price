/**
 * NS Tour Price Calendar - Navigation JavaScript
 * 
 * 月送りナビゲーションの部分更新機能
 */
(function() {
    'use strict';

    // 相対→絶対URLヘルパー
    function toAbsUrl(href) {
        if (!href || href === '#') return new URL(window.location.href);
        try {
            return new URL(href, window.location.href); // 基準URLを必ず指定
        } catch (e) {
            return new URL(window.location.href); // フォールバック
        }
    }

    // 設定
    const CONFIG = {
        SELECTORS: {
            calendar: '.ns-tour-price-calendar',
            navButton: '.tpc-nav__btn',
            navLink: '.tpc-nav-link', // durationタブも含む汎用セレクター
            loading: '.tpc-loading',
        },
        CLASSES: {
            loading: 'tpc-loading',
            error: 'tpc-error',
        },
        API: {
            endpoint: '/wp-json/ns-tour-price/v1/calendar',
            timeout: 10000,
        }
    };

    // 初期化
    document.addEventListener('DOMContentLoaded', function() {
        initializeNavigation();
    });

    /**
     * ナビゲーション機能を初期化
     */
    function initializeNavigation() {
        const calendars = document.querySelectorAll(CONFIG.SELECTORS.calendar);
        
        calendars.forEach(function(calendar) {
            setupCalendarNavigation(calendar);
        });
    }

    /**
     * カレンダーにナビゲーション機能をセットアップ
     */
    function setupCalendarNavigation(calendar) {
        const navLinks = calendar.querySelectorAll(CONFIG.SELECTORS.navLink);
        
        navLinks.forEach(function(button) {
            // プリフェッチ設定
            button.addEventListener('mouseenter', function() {
                const url = button.getAttribute('href');
                if (url) {
                    prefetchCalendar(url);
                }
            });

            // クリックイベント
            button.addEventListener('click', function(e) {
                e.preventDefault();
                
                const url = button.getAttribute('href');
                if (url) {
                    loadCalendarViaAjax(calendar, url, button);
                }
            });
        });
    }

    /**
     * Ajax経由でカレンダーを読み込み
     */
    function loadCalendarViaAjax(calendar, url, clickedButton) {
        // ローディング状態開始
        setLoadingState(calendar, true);
        disableNavButtons(calendar, true);

        // URLとdata属性からパラメータを抽出
        const params = extractParamsFromUrl(toAbsUrl(url).href, clickedButton);
        const apiUrl = buildApiUrl(params);

        // Fetch API でリクエスト
        fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            },
            signal: AbortSignal.timeout(CONFIG.API.timeout)
        })
        .then(function(response) {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(function(data) {
            if (data.success && data.html) {
                updateCalendarContent(calendar, data.html);
                updateBrowserUrl(url);
                setupCalendarNavigation(calendar); // 新しいナビボタンにイベント再設定
            } else {
                throw new Error('Invalid response data');
            }
        })
        .catch(function(error) {
            console.error('Calendar load failed:', error);
            showError(calendar, 'カレンダーの読み込みに失敗しました');
            
            // フォールバック：通常のページ遷移
            setTimeout(function() {
                window.location.href = url;
            }, 1500);
        })
        .finally(function() {
            setLoadingState(calendar, false);
            disableNavButtons(calendar, false);
        });
    }

    /**
     * プリフェッチ機能（ホバー時）
     */
    function prefetchCalendar(url) {
        // キャッシュチェック
        if (isPrefetched(url)) {
            return;
        }

        const params = extractParamsFromUrl(toAbsUrl(url).href);
        const apiUrl = buildApiUrl(params);

        // バックグラウンドでフェッチ
        fetch(apiUrl, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        })
        .then(function(response) {
            if (response.ok) {
                markPrefetched(url);
            }
        })
        .catch(function(error) {
            // プリフェッチ失敗は無視
        });
    }

    /**
     * URLまたはdata属性からカレンダーパラメータを抽出
     */
    function extractParamsFromUrl(href, clickedButton = null) {
        const urlObj = toAbsUrl(href); // ← 相対でもOK
        const searchParams = urlObj.searchParams;
        
        // data属性から直接パラメータを取得（より確実）
        let params = {
            tour: searchParams.get('tour') || 'A1',
            month: searchParams.get('tpc_month') || getCurrentMonth(),
            duration: parseInt(searchParams.get('tpc_duration') || searchParams.get('duration') || '4', 10) || 4,
            heatmap: searchParams.get('heatmap') !== 'false',
            confirmed_only: searchParams.get('confirmed_only') === 'true',
            show_legend: searchParams.get('show_legend') !== 'false',
        };
        
        // クリックされたボタンのdata属性で上書き（優先）
        if (clickedButton) {
            const dataAttrs = clickedButton.dataset;
            if (dataAttrs.tour) params.tour = dataAttrs.tour;
            if (dataAttrs.month) params.month = dataAttrs.month;
            if (dataAttrs.duration) params.duration = parseInt(dataAttrs.duration, 10);
            if (dataAttrs.heatmap) params.heatmap = dataAttrs.heatmap !== '0';
            if (dataAttrs.confirmedOnly) params.confirmed_only = dataAttrs.confirmedOnly === '1';
            if (dataAttrs.showLegend) params.show_legend = dataAttrs.showLegend !== '0';
        }
        
        return params;
    }

    /**
     * API URLを構築
     */
    function buildApiUrl(params) {
        const url = new URL(CONFIG.API.endpoint, window.location.origin);
        
        Object.keys(params).forEach(function(key) {
            url.searchParams.append(key, params[key]);
        });
        
        return url.toString();
    }

    /**
     * カレンダーのHTMLコンテンツを更新
     */
    function updateCalendarContent(calendar, newHtml) {
        const parser = new DOMParser();
        const newDoc = parser.parseFromString(newHtml, 'text/html');
        const newCalendar = newDoc.querySelector(CONFIG.SELECTORS.calendar);
        
        if (newCalendar) {
            // 既存のカレンダーを置換
            calendar.innerHTML = newCalendar.innerHTML;
            
            // フェードイン効果
            calendar.style.opacity = '0';
            setTimeout(function() {
                calendar.style.transition = 'opacity 0.3s ease';
                calendar.style.opacity = '1';
            }, 50);
        }
    }

    /**
     * ブラウザURLを更新（履歴に追加）
     * フロントエンドURLのクエリパラメータとして維持
     */
    function updateBrowserUrl(url) {
        if (window.history && window.history.pushState) {
            // 相対URLの場合、現在のパス（RESTエンドポイント以外）にクエリを追加
            const targetUrl = toAbsUrl(url);
            const currentUrl = new URL(window.location.href);
            
            // RESTエンドポイントURLの場合は、フロントエンドページとして構築
            if (targetUrl.pathname.includes('/wp-json/')) {
                // クエリパラメータのみを現在のフロントページに適用
                const newUrl = new URL(currentUrl.pathname, window.location.origin);
                newUrl.search = targetUrl.search;
                window.history.pushState(null, '', newUrl.toString());
            } else {
                // 通常の相対URLの場合はそのまま使用
                window.history.pushState(null, '', targetUrl.toString());
            }
        }
    }

    /**
     * ローディング状態を設定
     */
    function setLoadingState(calendar, isLoading) {
        if (isLoading) {
            calendar.classList.add(CONFIG.CLASSES.loading);
            calendar.style.opacity = '0.6';
        } else {
            calendar.classList.remove(CONFIG.CLASSES.loading);
            calendar.style.opacity = '1';
        }
    }

    /**
     * ナビゲーションボタンを無効/有効化
     */
    function disableNavButtons(calendar, disabled) {
        const navLinks = calendar.querySelectorAll(CONFIG.SELECTORS.navLink);
        
        navLinks.forEach(function(button) {
            if (disabled) {
                button.style.pointerEvents = 'none';
                button.style.opacity = '0.5';
            } else {
                button.style.pointerEvents = '';
                button.style.opacity = '';
            }
        });
    }

    /**
     * エラーメッセージを表示
     */
    function showError(calendar, message) {
        const errorDiv = document.createElement('div');
        errorDiv.className = 'tpc-error-message';
        errorDiv.style.cssText = 'background:#fee;color:#c33;padding:10px;border:1px solid #fcc;margin:10px 0;border-radius:4px;';
        errorDiv.textContent = message;
        
        calendar.insertBefore(errorDiv, calendar.firstChild);
        
        // 3秒後に自動削除
        setTimeout(function() {
            if (errorDiv.parentNode) {
                errorDiv.parentNode.removeChild(errorDiv);
            }
        }, 3000);
    }

    /**
     * 現在の月を取得
     */
    function getCurrentMonth() {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        return year + '-' + month;
    }

    /**
     * プリフェッチ状態管理
     */
    const prefetchCache = new Set();

    function isPrefetched(url) {
        return prefetchCache.has(url);
    }

    function markPrefetched(url) {
        prefetchCache.add(url);
    }

    // 年間ビュー機能
    const AnnualView = {
        cache: new Map(), // 年間ビューのキャッシュ (key: tour:duration:year, value: html)
        isEnabled: false,
        currentData: null,

        /**
         * 年間ビューを初期化
         */
        init: function() {
            this.setupToggleButton();
            this.bindEvents();
        },

        /**
         * トグルボタンを設定
         */
        setupToggleButton: function() {
            const calendars = document.querySelectorAll(CONFIG.SELECTORS.calendar);
            
            calendars.forEach(function(calendar) {
                // 年間ビュー切替チェックボックスを追加
                const header = calendar.querySelector('.calendar-header');
                if (header && !header.querySelector('.tpc-annual-toggle')) {
                    const toggleContainer = document.createElement('div');
                    toggleContainer.className = 'tpc-annual-toggle';
                    toggleContainer.innerHTML = `
                        <label>
                            <input type="checkbox" id="tpc-annual-checkbox"> 年間価格概要を表示
                        </label>
                    `;
                    
                    header.appendChild(toggleContainer);
                    
                    // 年間ビュー表示エリアを追加
                    const annualRoot = document.createElement('div');
                    annualRoot.id = 'tpc-annual-root';
                    annualRoot.className = 'tpc-annual-root';
                    calendar.appendChild(annualRoot);
                }
            });
        },

        /**
         * イベントを設定
         */
        bindEvents: function() {
            document.addEventListener('change', function(e) {
                if (e.target.id === 'tpc-annual-checkbox') {
                    AnnualView.isEnabled = e.target.checked;
                    
                    if (AnnualView.isEnabled) {
                        AnnualView.loadAnnualView();
                    } else {
                        AnnualView.hideAnnualView();
                    }
                }
            });
        },

        /**
         * 年間ビューを読み込み
         */
        loadAnnualView: function() {
            const calendar = document.querySelector(CONFIG.SELECTORS.calendar);
            if (!calendar) return;

            const tour = calendar.dataset.tour || 'A1';
            const duration = parseInt(calendar.dataset.duration) || 4;
            const month = calendar.dataset.month || getCurrentMonth();
            const year = parseInt(month.substring(0, 4));

            this.currentData = { tour, duration, year };
            
            // キャッシュチェック
            const cacheKey = `${tour}:${duration}:${year}`;
            if (this.cache.has(cacheKey)) {
                this.displayAnnualView(this.cache.get(cacheKey));
                return;
            }

            // Ajax で年間データを取得
            this.fetchAnnualData(tour, duration, year);
        },

        /**
         * Ajax で年間データを取得
         */
        fetchAnnualData: function(tour, duration, year) {
            const apiUrl = new URL('/wp-json/ns-tour-price/v1/annual', window.location.origin);
            
            fetch(apiUrl, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    tour: tour,
                    duration: duration,
                    year: year,
                    show: true
                })
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(data) {
                if (data.success && data.html) {
                    // キャッシュに保存
                    const cacheKey = `${tour}:${duration}:${year}`;
                    AnnualView.cache.set(cacheKey, data.html);
                    
                    AnnualView.displayAnnualView(data.html);
                } else {
                    throw new Error('Invalid response data');
                }
            })
            .catch(function(error) {
                console.error('Annual view load failed:', error);
                AnnualView.displayAnnualView('<div class="tpc-annual-error">年間概要の読み込みに失敗しました</div>');
            });
        },

        /**
         * 年間ビューを表示
         */
        displayAnnualView: function(html) {
            const annualRoot = document.getElementById('tpc-annual-root');
            if (annualRoot) {
                annualRoot.innerHTML = html;
                annualRoot.style.display = 'block';
            }
        },

        /**
         * 年間ビューを非表示
         */
        hideAnnualView: function() {
            const annualRoot = document.getElementById('tpc-annual-root');
            if (annualRoot) {
                annualRoot.style.display = 'none';
            }
        },

        /**
         * 月送り・日数タブ切替時の自動更新
         */
        updateForNavigation: function(tour, duration, month) {
            if (!this.isEnabled) return;

            const year = parseInt(month.substring(0, 4));
            
            // データが変わった場合のみ更新
            if (!this.currentData || 
                this.currentData.tour !== tour || 
                this.currentData.duration !== duration || 
                this.currentData.year !== year) {
                
                this.currentData = { tour, duration, year };
                this.loadAnnualView();
            }
        }
    };

    // 既存のカレンダー更新時に年間ビューも更新
    const originalUpdateCalendarContent = updateCalendarContent;
    updateCalendarContent = function(calendar, newHtml) {
        originalUpdateCalendarContent(calendar, newHtml);
        
        // 更新されたカレンダーから情報を取得して年間ビューを更新
        const tour = calendar.dataset.tour || 'A1';
        const duration = parseInt(calendar.dataset.duration) || 4;
        const month = calendar.dataset.month || getCurrentMonth();
        
        AnnualView.updateForNavigation(tour, duration, month);
    };

    // DOMContentLoaded で年間ビューを初期化
    document.addEventListener('DOMContentLoaded', function() {
        AnnualView.init();
    });

})();