#!/bin/bash
# lightcheck.sh - 軽量構文チェック（省トークン版）
# Claude Code 用の最低限チェック

echo "lightcheck.sh: 軽量構文チェック開始"

# PHP 構文チェック
if command -v php >/dev/null 2>&1; then
    echo "PHP構文チェック中..."
    find . -name "*.php" -not -path "./.*" -not -path "./vendor/*" -print0 | while read -d $'\0' file; do
        php -l "$file" >/dev/null 2>&1
        if [ $? -ne 0 ]; then
            echo "構文エラー: $file"
        fi
    done
    echo "PHP構文チェック完了"
else
    echo "PHP構文チェック: skip (php not found)"
fi

# CSV ヘッダーチェック（最低限）
echo "CSV基本チェック中..."
for csv in $(find . -name "*.csv" -not -path "./.*"); do
    if [ -f "$csv" ] && [ -s "$csv" ]; then
        header=$(head -1 "$csv")
        if [[ "$header" == *"tour_id"* ]] && [[ "$header" == *"date"* ]]; then
            echo "CSV OK: $csv"
        else
            echo "CSV ヘッダー不正: $csv"
        fi
    fi
done

echo "lightcheck.sh: 完了"