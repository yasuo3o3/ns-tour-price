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
        const navButtons = calendar.querySelectorAll(CONFIG.SELECTORS.navButton);
        
        navButtons.forEach(function(button) {
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

        // URLからパラメータを抽出
        const params = extractParamsFromUrl(toAbsUrl(url).href);
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
     * URLからカレンダーパラメータを抽出
     */
    function extractParamsFromUrl(href) {
        const urlObj = toAbsUrl(href); // ← 相対でもOK
        const searchParams = urlObj.searchParams;
        
        return {
            tour: searchParams.get('tour') || 'A1',
            month: searchParams.get('tpc_month') || getCurrentMonth(),
            duration: parseInt(searchParams.get('duration') || '4', 10) || 0,
            heatmap: searchParams.get('heatmap') !== 'false',
            confirmed_only: searchParams.get('confirmed_only') === 'true',
            show_legend: searchParams.get('show_legend') !== 'false',
        };
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
     */
    function updateBrowserUrl(url) {
        if (window.history && window.history.pushState) {
            window.history.pushState(null, '', url);
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
        const navButtons = calendar.querySelectorAll(CONFIG.SELECTORS.navButton);
        
        navButtons.forEach(function(button) {
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

})();