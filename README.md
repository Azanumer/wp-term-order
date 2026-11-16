# Term Order

Drag-and-drop ordering for taxonomy terms — categories, tags and custom taxonomies. The order you set in the admin is the order `get_terms()` and `wp_list_categories()` return on the front end.

## Features

- **Drag-and-drop** — reorder term rows directly on the taxonomy list screen (jQuery UI Sortable, already bundled with WordPress); the order saves via AJAX as term meta.
- **Order column** — an "Order" column on every enabled taxonomy's list table, with a drag handle.
- **Front-end ordering** — `get_terms()` calls for enabled taxonomies (without an explicit `orderby`) return terms in your manual order automatically. Pass an explicit `orderby` and it wins.
- **Per-taxonomy control** — choose which taxonomies get ordering under **Settings → Term Order** (categories by default). New terms get the next order value automatically.
- **Safe by design** — sorting only activates on the plain list view (not while searching or sorting by another column); the AJAX handler checks a nonce and the `manage_categories` capability, and order values are stored with 10-step gaps so a single drag never needs a full renumber.

## Installation

1. Upload the `wp-term-order` folder to `/wp-content/plugins/`.
2. Activate the plugin through **Plugins** in WordPress admin.
3. Pick your taxonomies under **Settings → Term Order**, then drag rows on the taxonomy list screen.

## Filters

- `wto_taxonomies` — override the list of ordered taxonomies in code.
- `wto_apply_term_order` — return `false` to skip manual ordering for a specific `get_terms()` call.

## Uninstall

Deleting the plugin removes the `wto_settings` option and all `wto_order` term meta.

## License

MIT — see [LICENSE](LICENSE).
