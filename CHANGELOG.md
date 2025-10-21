# Changelog

All notable changes to ItemSetGroup will be documented in this file.

This project adheres to Keep a Changelog and Semantic Versioning.

## [0.2.3] - 2025-10-21

### Added
- Admin UX: Each "Selection X" section in the Selection block form is now collapsible (accordion).
  - Default: Selection 1 is open; all others are closed.
  - Click the legend button to toggle. Keyboard accessible (button element, updates aria-expanded).
  - Persist open/closed state per page and block via localStorage; restores on next visit.
  - Global controls: "Open all" / "Close all" buttons to expand/collapse all sections at once (also persisted).

### 日本語

#### 追加
- 管理UI: Selection ブロックの「Selection X」セクションをアコーディオン化し、個別に開閉できるようにしました。
  - 既定では「Selection 1」のみ開、2以降は閉。
  - 見出しのボタンをクリック（またはキーボード操作）で開閉できます（aria-expanded を更新）。
  - ページ/ブロック単位で開閉状態を localStorage に保存し、次回表示時に復元します。
  - 「すべて開く」「すべて閉じる」ボタンを追加し、一括で開閉できます（保存も反映）。

## [0.2.2] - 2025-10-21

### Added
- Selection block: Public-facing HTML title overrides for both Item set and Child item.
  - New fields in admin: "Item set title (public, HTML)", "Child item title (public, HTML)"
  - Newlines are converted to <br> on the public side; otherwise HTML is rendered as-is.
  - Auto-fill: when selecting an Item set or Child item, if the corresponding HTML field is empty, copy the chosen title.
  - Clear button also clears the child HTML title field.
- Frontend template updated to prefer the HTML title overrides; falls back to original titles if empty.

### Kept
- Child item check icon in the selection block remains unchanged.

### 日本語

#### 追加
- Selection ブロック: 公開用の HTML タイトル上書き（アイテムセット／子アイテム）を実装。
  - 管理UIに「Item set title (public, HTML)」「Child item title (public, HTML)」を追加。
  - 公開側では改行のみ <br> に変換し、それ以外の HTML はそのままレンダリング。
  - 自動複写: アイテムセット／子アイテムを選択した際、該当の HTML フィールドが空ならタイトルを自動入力。
  - クリア時は子アイテムの HTML タイトルも空にします。
- フロントテンプレートは HTML タイトルを優先。空のときは従来のタイトルを使用。

#### 維持
- 子アイテムのチェックアイコンは従来通り表示（変更なし）。

## [0.2.1] - 2025-10-21

### Added
- Selection block: Provide a scoped CSS override so titles are not truncated with ellipsis inside tiles; long titles now wrap naturally.

### Changed
- Theme integration (foundation_tsukuba2025): Standardized sort label to "Created" for consistent translation in Japanese.
- Theme independence: Ensure the groups layout template (`omeka/site/item-set/browse-groups`) is selected during dispatch whenever `groups_route=true` or `layout=groups` is present. This lets the module work without theme support while still allowing theme overrides.

### 日本語

#### 追加
- Selection ブロック: タイル内のタイトルが省略記号（…）で切れないよう、ブロックスコープでオーバーライドする CSS を追加。長いタイトルは自然に折り返します。

#### 変更
- テーマ連携（foundation_tsukuba2025）: ソートラベルを「Created」に統一し、日本語翻訳の整合性を改善。
- テーマ非依存性: `groups_route=true` または `layout=groups` のとき、ディスパッチ時に `omeka/site/item-set/browse-groups` テンプレートを強制選択。テーマが未対応でも本モジュール単体で機能し、テーマ側の上書きも可能です。

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
[0.2.1]: https://github.com/wakitosh/ItemSetGroup/releases/tag/v0.2.1
