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
            annualCheckbox: '.tpc-annual-checkbox',
            annualRoot: '.tpc-annual-root',
        },
        CLASSES: {
            loading: 'tpc-loading',
            error: 'tpc-error',
        },
        API: {
            endpoint: '/wp-json/ns-tour-price/v1/calendar',
            annualEndpoint: '/wp-json/ns-tour-price/v1/annual',
            timeout: 10000,
        }
    };

    // 年間ビューキャッシュ
    const annualCache = new Map();

    // 初期化
    document.addEventListener('DOMContentLoaded', function() {
        initializeNavigation();
    });

    /**
     * ナビゲーション機能を初期化
     */
    function initializeNavigation() {
        const calendars = document.querySelectorAll(CONFIG.SELECTORS.calendar);
        
        // TPC機能を最初に初期化（高優先度のイベントハンドラー）
        TPC.init();
        
        calendars.forEach(function(calendar) {
            setupCalendarNavigation(calendar);
        });
        
        // 年間ビュー機能の初期化（チェックボックス生成含む）
        AnnualView.init();
        
        calendars.forEach(function(calendar) {
            setupAnnualView(calendar);
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
                console.log('setupCalendarNavigation click handler called for:', button); // デバッグ用
                
                // 日数タブの場合はTPC側で処理するため無視
                if (button.closest('[data-tpc-duration-tabs]') || button.classList.contains('tpc-duration-tab')) {
                    console.log('Duration tab detected, skipping navigation handler'); // デバッグ用
                    return;
                }
                
                e.preventDefault();
                
                const url = button.getAttribute('href');
                if (url) {
                    loadCalendarViaAjax(calendar, url, button);
                }
            });
        });
    }

    /**
     * 年間ビュー機能をセットアップ
     */
    function setupAnnualView(calendar) {
        const checkbox = calendar.querySelector(CONFIG.SELECTORS.annualCheckbox);
        if (!checkbox) return;

        checkbox.addEventListener('change', function() {
            if (this.checked) {
                loadAnnualView(calendar);
            } else {
                hideAnnualView(calendar);
            }
        });
    }

    /**
     * 年間ビューを読み込み
     */
    function loadAnnualView(calendar) {
        const tour = calendar.dataset.tour || 'A1';
        const duration = parseInt(calendar.dataset.duration) || 4;
        const month = calendar.dataset.month || getCurrentMonth();
        const year = parseInt(month.substring(0, 4));

        const cacheKey = `${tour}-${duration}-${year}`;
        
        // キャッシュチェック
        if (annualCache.has(cacheKey)) {
            displayAnnualView(calendar, annualCache.get(cacheKey));
            return;
        }

        // API呼び出し
        const params = new URLSearchParams({
            tour: tour,
            duration: duration,
            year: year,
            show: true
        });

        fetch(CONFIG.API.annualEndpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: params,
            signal: AbortSignal.timeout(CONFIG.API.timeout)
        })
        .then(response => {
            if (!response.ok) {
                throw new Error('HTTP ' + response.status);
            }
            return response.json();
        })
        .then(data => {
            if (data.success && data.html) {
                annualCache.set(cacheKey, data.html);
                displayAnnualView(calendar, data.html);
            } else {
                throw new Error('Invalid annual view data');
            }
        })
        .catch(error => {
            console.error('Annual view load failed:', error);
            showAnnualError(calendar, '年間ビューの読み込みに失敗しました');
        });
    }

    /**
     * 年間ビューを表示
     */
    function displayAnnualView(calendar, html) {
        let annualRoot = calendar.querySelector(CONFIG.SELECTORS.annualRoot);
        if (!annualRoot) {
            annualRoot = document.createElement('div');
            annualRoot.className = 'tpc-annual-root';
            calendar.appendChild(annualRoot);
        }
        
        annualRoot.innerHTML = html;
        annualRoot.style.display = 'block';
    }

    /**
     * 年間ビューを非表示
     */
    function hideAnnualView(calendar) {
        const annualRoot = calendar.querySelector(CONFIG.SELECTORS.annualRoot);
        if (annualRoot) {
            annualRoot.style.display = 'none';
        }
    }

    /**
     * 年間ビューエラー表示
     */
    function showAnnualError(calendar, message) {
        let annualRoot = calendar.querySelector(CONFIG.SELECTORS.annualRoot);
        if (!annualRoot) {
            annualRoot = document.createElement('div');
            annualRoot.className = 'tpc-annual-root';
            calendar.appendChild(annualRoot);
        }
        
        annualRoot.innerHTML = `<div class="tpc-annual-error">${message}</div>`;
        annualRoot.style.display = 'block';
    }

    /**
     * ナビゲーション更新時の年間ビュー更新
     */
    function updateAnnualViewIfNeeded(calendar) {
        const checkbox = calendar.querySelector(CONFIG.SELECTORS.annualCheckbox);
        if (checkbox && checkbox.checked) {
            loadAnnualView(calendar);
        }
    }

    /**
     * 現在月取得
     */
    function getCurrentMonth() {
        const now = new Date();
        return now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
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
                AnnualView.setupToggleButton(); // 年間ビュートグルボタンを再設定
                setupAnnualView(calendar); // 年間ビューも再設定
                TPC.bindEvents(); // TPC日付クリックイベントも再設定
                updateAnnualViewIfNeeded(calendar);
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
                // 年間ビュー切替チェックボックスを確認・追加
                const header = calendar.querySelector('.calendar-header');
                if (header && !calendar.querySelector('.tpc-annual-toggle')) {
                    const toggleContainer = document.createElement('div');
                    toggleContainer.className = 'tpc-annual-toggle';
                    toggleContainer.innerHTML = `
                        <label>
                            <input type="checkbox" id="tpc-annual-checkbox"> 年間価格概要を表示
                        </label>
                    `;
                    
                    // templates/calendar.phpで凡例下に配置済みのため、カレンダー末尾に追加
                    calendar.appendChild(toggleContainer);
                }
                
                // 年間ビュー表示エリアを追加
                if (!calendar.querySelector('#tpc-annual-root')) {
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

    // TPC予約パネル初期化は initializeNavigation() で実行済み

    // TPC 予約パネル機能
    const TPC = {
        state: {
            tour: null,
            date: null,
            duration: 4,
            pax: 1,
        },
        
        els: {},
        eventsInitialized: false,
        
        init: function() {
            this.findElements();
            this.bindEvents();
            this.initState();
        },
        
        findElements: function() {
            this.els = {
                date: document.querySelector('[data-tpc-date]'),
                weekday: document.querySelector('[data-tpc-weekday]'),
                season: document.querySelector('[data-tpc-season]'),
                base: document.querySelector('[data-tpc-base]'),
                solo: document.querySelector('[data-tpc-solo]'),
                paxView: document.querySelector('[data-tpc-pax-view]'),
                total: document.querySelector('[data-tpc-total]'),
                submit: document.querySelector('[data-tpc-submit]'),
                paxSelect: document.querySelector('[data-tpc-pax]'),
                durationTabs: document.querySelectorAll('[data-tpc-duration-tabs] button'),
                calendar: document.querySelector('.ns-tour-price-calendar')
            };
        },
        
        bindEvents: function() {
            // すでにイベントが設定済みの場合は何もしない
            if (this.eventsInitialized) {
                console.log('TPC events already initialized, skipping'); // デバッグ用
                return;
            }
            
            console.log('Initializing TPC event handlers'); // デバッグ用
            
            // 大カレンダーの日付クリック（イベント委譲）
            document.addEventListener('click', this.handleDateClick.bind(this));
            
            // 期間タブクリック（イベント委譲、キャプチャフェーズで高優先度）
            document.addEventListener('click', this.handleDurationClick.bind(this), true);
            
            // 人数セレクト変更
            if (this.els.paxSelect) {
                this.els.paxSelect.addEventListener('change', this.handlePaxChange.bind(this));
            }
            
            this.eventsInitialized = true;
            console.log('TPC event handlers initialized'); // デバッグ用
        },
        
        initState: function() {
            const calendar = this.els.calendar;
            if (calendar) {
                this.state.tour = calendar.dataset.tour || 'A1';
                this.state.duration = parseInt(calendar.dataset.duration) || 4;
            }
            
            this.renderEmpty();
        },
        
        handleDateClick: function(e) {
            // カレンダー内のクリックのみ処理
            if (!e.target.closest('.ns-tour-price-calendar')) {
                return;
            }
            
            console.log('Date click event fired', e.target); // デバッグ用
            
            // 年カレンダーは無視
            if (e.target.closest('[data-static-annual="1"]')) {
                console.log('Annual calendar ignored');
                return;
            }
            
            const dayCell = e.target.closest('.calendar-day.has-price');
            console.log('Day cell found:', dayCell); // デバッグ用
            
            if (dayCell) {
                const date = dayCell.dataset.date;
                console.log('Date selected:', date, 'Previous date:', this.state.date); // デバッグ用
                
                if (date) {
                    this.state.date = date;
                    this.refreshQuote();
                }
            }
        },
        
        handleDurationClick: function(e) {
            console.log('TPC handleDurationClick called for:', e.target); // デバッグ用
            
            // 日数タブのクリックのみ処理（button または a要素）
            const tab = e.target.closest('[data-tpc-duration-tabs] button') || 
                       e.target.closest('.tpc-duration-tab[data-duration]');
            if (!tab) {
                console.log('Not a duration tab, ignoring'); // デバッグ用
                return;
            }
            
            console.log('Duration tab detected, stopping all other handlers...'); // デバッグ用
            e.preventDefault();
            e.stopPropagation(); 
            e.stopImmediatePropagation(); // 同じフェーズの他のハンドラーも停止
            const duration = parseInt(tab.dataset.duration);
            const tabLocation = tab.closest('.tpc-booking-panel') ? 'aside' : 'main';
            console.log('Duration tab clicked:', duration, 'Location:', tabLocation); // デバッグ用
            
            if (duration && duration !== this.state.duration) {
                // 全ての日数タブの見た目を更新（メインとaside両方）
                const allDurationTabs = document.querySelectorAll('[data-tpc-duration-tabs] button, .tpc-duration-tab[data-duration]');
                allDurationTabs.forEach(function(t) {
                    t.classList.remove('is-active');
                    t.removeAttribute('aria-current');
                });
                
                // 同じduration値のタブを全てアクティブにする
                allDurationTabs.forEach(function(t) {
                    if (parseInt(t.dataset.duration) === duration) {
                        t.classList.add('is-active');
                        t.setAttribute('aria-current', 'page');
                    }
                });
                
                this.state.duration = duration;
                this.refreshQuote();
                this.refreshCalendar();
            }
        },
        
        handlePaxChange: function(e) {
            this.state.pax = parseInt(e.target.value) || 1;
            this.refreshQuote();
        },
        
        async refreshQuote() {
            console.log('refreshQuote called with state:', this.state); // デバッグ用
            
            if (!this.state.date) {
                this.renderEmpty();
                return;
            }
            
            try {
                const payload = {
                    tour: this.state.tour,
                    date: this.state.date,
                    duration: this.state.duration,
                    pax: this.state.pax
                };
                
                const response = await fetch('/wp-json/ns-tour-price/v1/quote', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify(payload)
                });
                
                const json = await response.json();
                
                if (json.success) {
                    this.renderQuote(json);
                } else {
                    this.renderError(json.message || 'エラーが発生しました');
                }
                
            } catch (error) {
                console.error('Quote API error:', error);
                this.renderError('通信エラーが発生しました');
            }
        },
        
        renderQuote: function(data) {
            console.log('Quote data received:', data); // デバッグ用

            if (this.els.date) {
                this.els.date.textContent = this.formatDateJP(this.state.date);
            }
            if (this.els.weekday) {
                this.els.weekday.textContent = this.formatWeekdayJP(this.state.date);
            }
            if (this.els.season) {
                this.els.season.textContent = data.season_code ? 'シーズン: ' + data.season_code : '';
            }
            if (this.els.base) {
                this.els.base.textContent = this.formatYen(data.base_price || 0);
            }
            if (this.els.solo) {
                this.els.solo.textContent = data.solo_fee ? this.formatYen(data.solo_fee) : '—';
            }
            if (this.els.paxView) {
                this.els.paxView.textContent = data.pax + '名';
            }
            if (this.els.total) {
                this.els.total.textContent = this.formatYen(data.total);
            }
            if (this.els.submit) {
                this.els.submit.disabled = false;
            }
        },
        
        refreshCalendar: function() {
            const calendar = this.els.calendar;
            if (!calendar) return;
            
            // カレンダーのdata-durationを更新
            calendar.dataset.duration = this.state.duration;
            
            // カレンダーを再読み込み
            const tour = this.state.tour;
            const currentMonth = calendar.dataset.month || new Date().toISOString().slice(0, 7);
            console.log('refreshCalendar - month:', currentMonth, 'duration:', this.state.duration); // デバッグ用
            const url = '/wp-json/ns-tour-price/v1/calendar?' + 
                       new URLSearchParams({
                           tour: tour,
                           duration: this.state.duration,
                           month: currentMonth
                       }).toString();
            
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (data.success && data.html) {
                        updateCalendarContent(calendar, data.html);
                        AnnualView.setupToggleButton(); // 年間ビュートグルボタンを再設定
                        setupAnnualView(calendar); // 年間ビューも再設定
                        TPC.bindEvents(); // TPC日付クリックイベントも再設定
                    }
                })
                .catch(error => {
                    console.error('Calendar refresh failed:', error);
                });
        },
        
        renderEmpty: function() {
            if (this.els.date) {
                this.els.date.textContent = '未選択';
            }
            if (this.els.weekday) {
                this.els.weekday.textContent = '';
            }
            if (this.els.season) {
                this.els.season.textContent = '';
            }
            if (this.els.base) {
                this.els.base.textContent = '—';
            }
            if (this.els.solo) {
                this.els.solo.textContent = '—';
            }
            if (this.els.paxView) {
                this.els.paxView.textContent = '—';
            }
            if (this.els.total) {
                this.els.total.textContent = '—';
            }
            if (this.els.submit) {
                this.els.submit.disabled = true;
            }
        },
        
        renderError: function(message) {
            if (this.els.total) {
                this.els.total.textContent = 'エラー';
            }
            console.error('TPC Quote Error:', message);
        },
        
        formatDateJP: function(dateStr) {
            try {
                const date = new Date(dateStr);
                const year = date.getFullYear();
                const month = date.getMonth() + 1;
                const day = date.getDate();
                return year + '年' + month + '月' + day + '日';
            } catch (e) {
                return dateStr;
            }
        },

        formatWeekdayJP: function(dateStr) {
            try {
                const date = new Date(dateStr);
                const weekdays = ['日', '月', '火', '水', '木', '金', '土'];
                return '(' + weekdays[date.getDay()] + ')';
            } catch (e) {
                return '';
            }
        },
        
        formatYen: function(amount) {
            try {
                return '¥' + Number(amount).toLocaleString();
            } catch (e) {
                return '¥' + amount;
            }
        }
    };

})();