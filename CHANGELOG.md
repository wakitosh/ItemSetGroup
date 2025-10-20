# Changelog

All notable changes to ItemSetGroup will be documented in this file.

This project adheres to Keep a Changelog and Semantic Versioning.

## [0.2.0] - 2025-10-20

### Added
- Pretty group routes: `/s/:site-slug/item-set-group[/:parent]` and `/item-set-group[/:parent]` now dispatch directly to Omeka's Site ItemSet `browse` with `groups_route=true` for a clean URL and stable rendering.
- Anonymous-safe browsing for groups: Groups pages no longer hit a custom controller, avoiding ACL issues for guests.
- Theme integration updates (foundation_tsukuba2025):
  - Groups browse normalization always uses `/item-set-group` as base and preserves parent ID.
  - Do not append `layout=groups` when already under `/item-set-group`.
  - Sort labels switched to translatable keys: `Title`, `Date created`.
- Selection block (Item Set Group: Selection):
  - Default “more” text when URL is set and text is empty (ja: 「もっと見る」, otherwise: “See more”).
  - Exclude group-parent item sets from selection candidates; candidates sorted by ID ascending.
- Representative item/media support:
  - Admin UI to pick representative Item/Media for each Item set (stored in custom table `item_set_primary_item`).
  - View helper `itemSetPrimaryThumb` to render primary thumbnails with IIIF-aware sizing and placeholder fallback.
- Admin UX hardening: Prevent unintended navigation from the sidebar, restore parent/children filtering, persist selections.

### Changed
- Routing priority increased for `/item-set-group` routes to ensure they are matched before generic ones.
- For anonymous users, removed internal forwards; use direct dispatch (site route) or safe redirects where needed.

### Fixed
- Resolved `PermissionDenied`/500 for guests accessing `/item-set-group` by bypassing the custom controller and normalizing URLs safely.
- Stabilized thumbnail rendering with a robust placeholder fallback.

### Notes / Compatibility
- If an older module providing similar routes (e.g., `GroupsBrowseRoute`) is enabled, disable or uninstall it to avoid route conflicts.
- Deep links that previously relied on `?layout=groups` under `/item-set-group` no longer need that parameter.

### 日本語

#### 追加
- きれいなグループ用ルート: `/s/:site-slug/item-set-group[/:parent]` と `/item-set-group[/:parent]` は、Omeka の Site ItemSet の `browse` に直接ディスパッチし、`groups_route=true` を付与。整ったURLと安定した表示を実現。
- 匿名でも安全なグループ閲覧: カスタムコントローラを経由しないため、ゲストでも ACL に阻まれず閲覧可能。
- テーマ統合（foundation_tsukuba2025）:
  - グループ一覧の正規化は常に `/item-set-group` をベースにし、親IDを保持。
  - `/item-set-group` 配下では `layout=groups` を付けない。
  - ソートラベルを翻訳可能キーに統一（`Title` / `Date created`）。
- Selection ブロック（Item Set Group: Selection）:
  - more_url が設定され text が空のときの既定文言（ja:「もっと見る」、その他: “See more”）。
  - グループ親のアイテムセットを候補から除外。候補は ID 昇順で整列。
- 代表アイテム/メディア:
  - 管理画面で各アイテムセットの代表 Item/Media を選択（カスタムテーブル `item_set_primary_item` に保存）。
  - ビューヘルパー `itemSetPrimaryThumb` により IIIF 対応サムネイルとプレースホルダーのフォールバックを提供。

#### 変更
- `/item-set-group` ルートの優先度を上げ、汎用ルートより前に確実にマッチするように調整。
- 匿名時の内部 forward を廃止し、サイトルートへの直接ディスパッチ／安全なリダイレクトに統一。

#### 修正
- ゲストの `/item-set-group` アクセスで発生していた `PermissionDenied`／500 を解消（カスタムコントローラを回避し、安全な正規化を実施）。
- サムネイル表示を安定化（プレースホルダーの確実なフォールバックを追加）。

#### 注意 / 互換性
- 同様のルートを提供する旧モジュール（例: `GroupsBrowseRoute`）が有効な場合は、ルート競合を避けるため無効化／アンインストールを推奨。
- `/item-set-group` 配下では `?layout=groups` は不要です。

## [0.1.0] - 2025-10-01
- Initial release with basic Selection block, representative thumbnail helper, and groups browse layout.

### 日本語
- 初回リリース: 基本的な Selection ブロック、代表サムネイル用ヘルパー、グループ一覧レイアウトを搭載。

[0.2.0]: https://github.com/wakitosh/ItemSetGroup/releases/tag/v0.2.0
[0.1.0]: https://github.com/wakitosh/ItemSetGroup/releases/tag/v0.1.0
