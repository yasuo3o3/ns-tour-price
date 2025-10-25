/**
 * NS Tour Price - Booking Preview JavaScript
 * 
 * 旅行内容選択フォームの即時計算とUI制御
 */
(function() {
    'use strict';

    // 設定
    const CONFIG = {
        SELECTORS: {
            form: '.tpc-booking-form',
            preview: '.tpc-booking-preview',
            durationTabs: '.tpc-duration-tab',
            durationInput: 'input[name="duration"]',
            paxInput: '.tpc-pax-input',
            paxBtn: '.tpc-pax-btn',
            optionCheckbox: 'input[name="options[]"]',
            submitBtn: '.tpc-submit-btn',
        },
        CLASSES: {
            loading: 'tpc-loading',
            active: 'active',
        },
        API: {
            endpoint: '/wp-json/ns-tour-price/v1/quote',
            timeout: 10000,
        },
        LIMITS: {
            maxPax: 20,
            minPax: 1,
        },
    };

    // 初期化
    document.addEventListener('DOMContentLoaded', function() {
        initializeBookingPreview();
    });

    /**
     * 予約プレビュー機能を初期化
     */
    function initializeBookingPreview() {
        const forms = document.querySelectorAll(CONFIG.SELECTORS.form);
        
        forms.forEach(function(form) {
            setupBookingForm(form);
        });
    }

    /**
     * フォームの機能をセットアップ
     */
    function setupBookingForm(form) {
        setupDurationTabs(form);
        setupPaxControls(form);
        setupOptionCheckboxes(form);
        setupSubmitForm(form);
        
        // 初期計算
        recalculatePrice(form);
    }

    /**
     * 日数タブの設定
     */
    function setupDurationTabs(form) {
        const tabs = form.querySelectorAll(CONFIG.SELECTORS.durationTabs);
        
        tabs.forEach(function(tab) {
            tab.addEventListener('click', function() {
                const input = tab.querySelector(CONFIG.SELECTORS.durationInput);
                if (input) {
                    // アクティブ状態を更新
                    tabs.forEach(t => t.classList.remove(CONFIG.CLASSES.active));
                    tab.classList.add(CONFIG.CLASSES.active);
                    
                    // 計算実行
                    recalculatePrice(form);
                }
            });
        });
    }

    /**
     * 人数コントロールの設定
     */
    function setupPaxControls(form) {
        const paxInput = form.querySelector(CONFIG.SELECTORS.paxInput);
        const paxButtons = form.querySelectorAll(CONFIG.SELECTORS.paxBtn);
        
        if (!paxInput) return;

        // 人数ボタン
        paxButtons.forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                
                const action = btn.dataset.action;
                let currentPax = parseInt(paxInput.value) || 1;
                
                if (action === 'plus' && currentPax < CONFIG.LIMITS.maxPax) {
                    currentPax++;
                } else if (action === 'minus' && currentPax > CONFIG.LIMITS.minPax) {
                    currentPax--;
                }
                
                paxInput.value = currentPax;
                updatePaxUI(form, currentPax);
                recalculatePrice(form);
            });
        });

        // 人数入力フィールド
        paxInput.addEventListener('change', function() {
            let pax = parseInt(this.value) || 1;
            
            // 範囲チェック
            if (pax < CONFIG.LIMITS.minPax) pax = CONFIG.LIMITS.minPax;
            if (pax > CONFIG.LIMITS.maxPax) pax = CONFIG.LIMITS.maxPax;
            
            this.value = pax;
            updatePaxUI(form, pax);
            recalculatePrice(form);
        });

        paxInput.addEventListener('input', function() {
            const pax = parseInt(this.value) || 1;
            updatePaxUI(form, pax);
        });
    }

    /**
     * 人数UIの更新
     */
    function updatePaxUI(form, pax) {
        // ソロフィー表示切替
        const soloFeeRow = form.querySelector('#tpc-solo-fee-row');
        const paxNotice = form.querySelector('.tpc-pax-notice');
        
        if (soloFeeRow) {
            soloFeeRow.style.display = pax === 1 ? 'flex' : 'none';
        }
        
        if (paxNotice) {
            paxNotice.style.display = pax === 1 ? 'block' : 'none';
        }

        // ボタンの有効/無効
        const minusBtn = form.querySelector('.tpc-pax-minus');
        const plusBtn = form.querySelector('.tpc-pax-plus');
        
        if (minusBtn) minusBtn.disabled = pax <= CONFIG.LIMITS.minPax;
        if (plusBtn) plusBtn.disabled = pax >= CONFIG.LIMITS.maxPax;
    }

    /**
     * オプションチェックボックスの設定
     */
    function setupOptionCheckboxes(form) {
        const checkboxes = form.querySelectorAll(CONFIG.SELECTORS.optionCheckbox);
        
        checkboxes.forEach(function(checkbox) {
            checkbox.addEventListener('change', function() {
                recalculatePrice(form);
            });
        });
    }

    /**
     * 申込フォーム送信の設定
     */
    function setupSubmitForm(form) {
        const submitBtn = form.querySelector(CONFIG.SELECTORS.submitBtn);
        
        if (submitBtn) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                
                // フォームデータを収集
                const formData = collectFormData(form);
                
                // 次画面へPOST送信（仮のURL）
                submitToBookingForm(formData);
            });
        }
    }

    /**
     * 価格を再計算
     */
    function recalculatePrice(form) {
        const formData = collectFormData(form);
        const preview = form.closest(CONFIG.SELECTORS.preview);
        
        if (!formData.tour || !formData.date) {
            console.warn('必要なデータが不足しています');
            return;
        }

        // API呼び出し
        const params = new URLSearchParams({
            tour: formData.tour,
            date: formData.date,
            duration: formData.duration,
            pax: formData.pax,
        });

        // オプション配列を追加
        formData.options.forEach(function(option) {
            params.append('options[]', option);
        });

        fetch(CONFIG.API.endpoint, {
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
            if (data.success) {
                updatePriceDisplay(form, data.data);
            } else {
                throw new Error('価格計算エラー');
            }
        })
        .catch(error => {
            console.error('Price calculation failed:', error);
            showPriceError(form, '料金計算に失敗しました');
        });
    }

    /**
     * フォームデータを収集
     */
    function collectFormData(form) {
        const preview = form.closest(CONFIG.SELECTORS.preview);
        
        // 基本データ
        const tour = form.querySelector('input[name="tour"]')?.value || 
                    preview?.dataset.tour || '';
        const date = form.querySelector('input[name="date"]')?.value || 
                    preview?.dataset.date || '';
        
        // 選択された日数
        const durationInput = form.querySelector(CONFIG.SELECTORS.durationInput + ':checked') ||
                             form.querySelector('input[name="duration"]');
        const duration = parseInt(durationInput?.value) || 4;
        
        // 人数
        const pax = parseInt(form.querySelector(CONFIG.SELECTORS.paxInput)?.value) || 1;
        
        // 出発地
        const departure = form.querySelector('select[name="departure"]')?.value || '成田';
        
        // オプション
        const options = Array.from(form.querySelectorAll(CONFIG.SELECTORS.optionCheckbox + ':checked'))
                            .map(checkbox => checkbox.value);

        return {
            tour: tour,
            date: date,
            duration: duration,
            pax: pax,
            departure: departure,
            options: options,
        };
    }

    /**
     * 価格表示を更新
     */
    function updatePriceDisplay(form, priceData) {
        const baseTotalElement = form.querySelector('#tpc-base-total');
        const soloFeeElement = form.querySelector('#tpc-solo-fee');
        const optionTotalElement = form.querySelector('#tpc-option-total');
        const totalPriceElement = form.querySelector('#tpc-total-price');
        
        // 各要素を更新
        if (baseTotalElement) {
            baseTotalElement.textContent = priceData.formatted.base_total;
        }
        
        if (soloFeeElement) {
            soloFeeElement.textContent = priceData.formatted.solo_fee;
        }
        
        if (optionTotalElement) {
            optionTotalElement.textContent = priceData.formatted.option_total;
            
            // オプション料金行の表示切替
            const optionRow = form.querySelector('#tpc-option-total-row');
            if (optionRow) {
                optionRow.style.display = priceData.option_total > 0 ? 'flex' : 'none';
            }
        }
        
        if (totalPriceElement) {
            totalPriceElement.textContent = priceData.formatted.total;
            
            // アニメーション効果
            totalPriceElement.style.transform = 'scale(1.05)';
            setTimeout(() => {
                totalPriceElement.style.transform = 'scale(1)';
            }, 200);
        }

        // 基本料金の詳細を更新
        const paxInput = form.querySelector(CONFIG.SELECTORS.paxInput);
        const durationInput = form.querySelector(CONFIG.SELECTORS.durationInput + ':checked') ||
                             form.querySelector('input[name="duration"]');
        
        if (paxInput && durationInput) {
            const pax = parseInt(paxInput.value) || 1;
            const duration = parseInt(durationInput.value) || 4;
            
            const basePriceLabel = form.querySelector('.tpc-price-label small');
            if (basePriceLabel) {
                basePriceLabel.textContent = `(${pax}名 × ${duration}日間)`;
            }
        }
    }

    /**
     * 価格エラー表示
     */
    function showPriceError(form, message) {
        const totalPriceElement = form.querySelector('#tpc-total-price');
        if (totalPriceElement) {
            totalPriceElement.textContent = '計算エラー';
            totalPriceElement.style.color = '#e53e3e';
        }
        
        console.error(message);
    }

    /**
     * 申込フォームへ送信
     */
    function submitToBookingForm(formData) {
        // 仮の申込フォームURL（実際の環境に応じて調整）
        const bookingFormUrl = '/booking-input/'; // または既存のフォームURL
        
        // POSTデータを作成
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = bookingFormUrl;
        form.style.display = 'none';
        
        // データを隠しフィールドとして追加
        Object.keys(formData).forEach(key => {
            if (Array.isArray(formData[key])) {
                // 配列の場合（オプション）
                formData[key].forEach(value => {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = key + '[]';
                    input.value = value;
                    form.appendChild(input);
                });
            } else {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = key;
                input.value = formData[key];
                form.appendChild(input);
            }
        });
        
        document.body.appendChild(form);
        form.submit();
    }

})();