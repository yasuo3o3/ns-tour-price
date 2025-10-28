=== andW Tour Price Calendar ===
Author: yasuo3o3
Author URI: https://yasuo-o.xyz/
Contributors: yasuo3o3
Tags: tour, price, calendar, travel, booking
Requires at least: 6.0
Tested up to: 6.4
Stable tag: 1.0.1
Requires PHP: 8.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

ツアー価格カレンダーを表示するWordPressプラグイン。CSV二段階探索とヒートマップ表示に対応。

== Description ==

andW Tour Price Calendarは、ツアーの価格情報をカレンダー形式で美しく表示するWordPressプラグインです。ヒートマップ表示によって価格の高低を視覚的に表現します。

= 主要機能 =

* **CSV二段階探索**: プラグインディレクトリとアップロードディレクトリの二段階でCSVファイルを探索
* **ヒートマップ表示**: 価格の高低を10段階の色分けで視覚化（青→緑→赤）
* **Gutenbergブロック対応**: エディタで簡単に挿入可能
* **ショートコード対応**: `[tour_price]` で任意の場所に配置可能
* **催行確定バッジ**: 催行確定日を視覚的に表示（ON/OFF設定可能）
* **レスポンシブデザイン**: モバイルデバイスでも美しく表示
* **キャッシュ機能**: 高速表示のためのTransientキャッシュ対応
* **国際化対応**: 多言語サイトでも使用可能

= CSVファイル構造 =

プラグインは以下の構造のCSVファイルを使用します：

**seasons.csv**
```
tour_id,season_code,label,date_start,date_end
A1,HIGH,ハイシーズン,2024-07-01,2024-08-31
```

**base_prices.csv**
```
tour_id,season_code,duration_days,price
A1,HIGH,4,150000
```

**daily_flags.csv** (オプション)
```
tour_id,date,is_confirmed,note
A1,2024-07-15,1,催行確定
```

= 使用方法 =

**ショートコード**
```
[tour_price tour="A1" month="2024-07" duration="4" heatmap="true" show_legend="true"]
```

**Gutenbergブロック**
エディタで「ツアー価格カレンダー」ブロックを選択し、属性を設定してください。

= パラメータ =

* `tour`: ツアーID（デフォルト: A1）
* `month`: 表示月（YYYY-MM形式、未指定時は現在月）
* `duration`: ツアー日数（デフォルト: 4）
* `heatmap`: ヒートマップ表示（true/false、デフォルト: true）
* `show_legend`: 凡例表示（true/false、デフォルト: true）
* `confirmed_only`: 催行確定日のみ表示（true/false、デフォルト: false）

== Installation ==

1. プラグインファイルを `/wp-content/plugins/andw-tour-price/` ディレクトリにアップロード
2. WordPressの管理画面で「プラグイン」メニューからプラグインを有効化
3. 「ツール」→「andW Tour Price」で設定を行う
4. CSVファイルを以下のいずれかに配置：
   - `/wp-content/plugins/andw-tour-price/data/` （開発用）
   - `/wp-content/uploads/andw-tour-price/` （本番用）

== Frequently Asked Questions ==

= CSVファイルはどこに配置すればよいですか？ =

以下の順序で探索されます：
1. `/wp-content/plugins/andw-tour-price/data/` （優先）
2. `/wp-content/uploads/andw-tour-price/` （フォールバック）

= 価格の色分けはどのように決まりますか？ =

月内の最安値から最高値を10段階に分割し、以下の色で表示されます：
- 安い価格: 青系
- 中程度: 緑系  
- 高い価格: 赤系

= 催行確定バッジが表示されません =

「ツール」→「andW Tour Price」の設定で「催行確定バッジ機能」を有効にしてください。また、daily_flags.csvファイルが正しく配置されているか確認してください。

= キャッシュをクリアしたい =

管理画面の「ツール」→「andW Tour Price」にある「キャッシュ削除」ボタンをクリックしてください。

== Screenshots ==

1. カレンダー表示例（ヒートマップ有効）
2. Gutenbergブロック設定画面
3. プラグイン設定画面
4. 催行確定バッジ表示例

== Changelog ==

= 1.0.0 =
* 初回リリース
* CSV二段階探索機能
* Gutenbergブロック対応
* ショートコード対応
* ヒートマップ表示機能
* 催行確定バッジ機能
* レスポンシブデザイン
* キャッシュ機能
* 国際化対応

== Upgrade Notice ==

= 1.0.0 =
初回リリースです。

== Developer Notes ==

= アクションフック =

* `andw_tour_price_before_calendar` - カレンダー表示前
* `andw_tour_price_after_calendar` - カレンダー表示後
* `andw_tour_price_cache_cleared` - キャッシュクリア時

= フィルターフック =

* `andw_tour_price_data_sources` - データソース配列
* `andw_tour_price_calendar_args` - カレンダー引数
* `andw_tour_price_price_format` - 価格フォーマット

= カスタムCSS =

カスタムCSSを追加する場合は、以下のクラスを使用してください：
- `.andw-tour-price-calendar` - カレンダー全体
- `.andw-tour-price-day` - 各日付セル
- `.hp-0` から `.hp-9` - ヒートマップクラス