# symbol-catbuffer-php-sdk

PHP 8.3 / PSR-12 / strict_types の **Symbol catbuffer ベース SDK**。
Prompt as Code で JS/Python SDK とパリティを取りつつ自動生成。

## Quick Start
- `composer install`
- `npm i`（生成ツール用）
- `.env` に `OPENAI_API_KEY=...`（Codex/互換モデルでも可）
- `npm run gen:mosaic-id` → `src/Model/MosaicId.php` が生成
- `composer test` / `composer analyse`


## Examples

SDK の利用例は `examples/` ディレクトリに配置しています。

### Transfer Transaction の送信
```bash
php examples/send_transfer.php