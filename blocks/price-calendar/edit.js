const { createElement: el, Fragment } = wp.element;
const { InspectorControls } = wp.blockEditor;
const { PanelBody, TextControl, SelectControl, ToggleControl, RangeControl } = wp.components;
const { __ } = wp.i18n;
const { ServerSideRender } = wp.serverSideRender;

export default function Edit({ attributes, setAttributes }) {
    const { tour, month, duration, showLegend, confirmedOnly, heatmap } = attributes;

    // 現在の年月を取得
    const getCurrentMonth = () => {
        const now = new Date();
        const year = now.getFullYear();
        const month = String(now.getMonth() + 1).padStart(2, '0');
        return `${year}-${month}`;
    };

    // 月のオプションを生成
    const generateMonthOptions = () => {
        const options = [{ label: __('現在月を使用', 'ns-tour_price'), value: '' }];
        const currentDate = new Date();
        
        // 過去6ヶ月
        for (let i = 6; i > 0; i--) {
            const date = new Date();
            date.setMonth(currentDate.getMonth() - i);
            const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
            const label = `${date.getFullYear()}年${date.getMonth() + 1}月`;
            options.push({ label, value });
        }

        // 現在月
        const currentValue = getCurrentMonth();
        const currentLabel = `${currentDate.getFullYear()}年${currentDate.getMonth() + 1}月（今月）`;
        options.push({ label: currentLabel, value: currentValue });

        // 未来12ヶ月
        for (let i = 1; i <= 12; i++) {
            const date = new Date();
            date.setMonth(currentDate.getMonth() + i);
            const value = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`;
            const label = `${date.getFullYear()}年${date.getMonth() + 1}月`;
            options.push({ label, value });
        }

        return options;
    };

    // ツアーオプション（実際のプラグインでは動的に取得）
    const tourOptions = [
        { label: 'ツアー A1', value: 'A1' },
        { label: 'ツアー B2', value: 'B2' },
        { label: 'ツアー C3', value: 'C3' },
    ];

    // 日数オプションを生成
    const durationOptions = [];
    for (let i = 1; i <= 14; i++) {
        durationOptions.push({
            label: `${i}日間`,
            value: i
        });
    }

    const inspectorControls = el(InspectorControls, {},
        el(PanelBody, {
            title: __('ツアー設定', 'ns-tour_price'),
            initialOpen: true
        },
            el(SelectControl, {
                label: __('ツアーID', 'ns-tour_price'),
                value: tour,
                options: tourOptions,
                onChange: (value) => setAttributes({ tour: value }),
                help: __('表示するツアーを選択してください', 'ns-tour_price')
            }),

            el(SelectControl, {
                label: __('表示月', 'ns-tour_price'),
                value: month,
                options: generateMonthOptions(),
                onChange: (value) => setAttributes({ month: value }),
                help: __('カレンダーを表示する月を選択してください', 'ns-tour_price')
            }),

            el(SelectControl, {
                label: __('ツアー日数', 'ns-tour_price'),
                value: duration,
                options: durationOptions,
                onChange: (value) => setAttributes({ duration: parseInt(value) }),
                help: __('ツアーの日数を選択してください', 'ns-tour_price')
            })
        ),

        el(PanelBody, {
            title: __('表示設定', 'ns-tour_price'),
            initialOpen: false
        },
            el(ToggleControl, {
                label: __('ヒートマップを表示', 'ns-tour_price'),
                checked: heatmap,
                onChange: (value) => setAttributes({ heatmap: value }),
                help: __('価格の高低を色で表示します', 'ns-tour_price')
            }),

            el(ToggleControl, {
                label: __('凡例を表示', 'ns-tour_price'),
                checked: showLegend,
                onChange: (value) => setAttributes({ showLegend: value }),
                help: __('ヒートマップの色分け凡例を表示します', 'ns-tour_price')
            }),

            el(ToggleControl, {
                label: __('催行確定日のみ表示', 'ns-tour_price'),
                checked: confirmedOnly,
                onChange: (value) => setAttributes({ confirmedOnly: value }),
                help: __('催行が確定している日付のみ表示します', 'ns-tour_price')
            })
        )
    );

    const blockContent = el('div', {
        className: 'ns-tour-price-block-editor'
    },
        el('div', {
            className: 'block-editor-header'
        },
            el('h4', {}, __('ツアー価格カレンダー', 'ns-tour_price')),
            el('p', {},
                __('ツアー: ', 'ns-tour_price') + tour + ' | ' +
                __('月: ', 'ns-tour_price') + (month || getCurrentMonth()) + ' | ' +
                __('日数: ', 'ns-tour_price') + duration + __('日', 'ns-tour_price')
            )
        ),

        el(ServerSideRender, {
            block: 'ns-tour_price/price-calendar',
            attributes: attributes,
            EmptyResponsePlaceholder: () => el('div', {
                className: 'block-editor-placeholder'
            },
                el('div', {
                    className: 'block-editor-placeholder__label'
                }, __('データを読み込み中...', 'ns-tour_price')),
                el('div', {
                    className: 'block-editor-placeholder__instructions'
                }, __('CSVファイルが正しく配置されていることを確認してください', 'ns-tour_price'))
            ),
            ErrorResponsePlaceholder: ({ response }) => el('div', {
                className: 'block-editor-error'
            },
                el('h5', {}, __('エラーが発生しました', 'ns-tour_price')),
                el('p', {}, response.message || __('不明なエラーです', 'ns-tour_price')),
                el('details', {},
                    el('summary', {}, __('詳細', 'ns-tour_price')),
                    el('pre', {}, JSON.stringify(response, null, 2))
                )
            ),
            LoadingResponsePlaceholder: () => el('div', {
                className: 'block-editor-loading'
            },
                el('div', { className: 'spinner' }),
                el('p', {}, __('カレンダーを読み込み中...', 'ns-tour_price'))
            )
        })
    );

    return el(Fragment, {},
        inspectorControls,
        blockContent
    );
}

wp.blocks.registerBlockType('ns-tour_price/price-calendar', {
    edit: Edit,
    save: () => null // Server-side renderingなのでnull
});