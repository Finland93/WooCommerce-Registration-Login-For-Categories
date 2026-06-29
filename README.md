# Registration for Categories — WooCommerce Plugin

Require **registration or login** at checkout when the cart contains products from selected categories, while still allowing **guest checkout** for everything else.

## How it works

1. Pick the product categories that should require an account.
2. When a guest's cart contains **any** product from those categories, WooCommerce requires them to register or log in before placing the order.
3. Carts without any restricted product can still check out as a guest.

## Setup

1. Make sure **WooCommerce** is active.
2. Under **WooCommerce → Settings → Accounts & Privacy**, enable guest checkout, login during checkout, and account creation during checkout.
3. Open **Registration for Categories**, tick the categories to restrict, and save.

## What changed in 2.0.0

- **Fixed the core logic bug.** 1.0 allowed guest checkout as soon as *any* item in the cart was outside the selected categories — so a restricted product slipped through whenever the cart also held an unrestricted one. The check now correctly asks "does the cart contain a restricted product?" and forces registration only for guests.
- **Fixed a settings bug.** The old paginated list overwrote the saved option with only the current page's checkboxes, wiping selections from other pages. The category list is now a single searchable, scrollable list, so every selection saves together.
- Added `get_the_terms()` guarding (1.0 could warn/fatal on products with no category), a WooCommerce dependency check, slug validation on save, escaping, i18n and a clean uninstall.

## License

GPLv2 or later — see [LICENSE](LICENSE).

**Author:** [Finland93](https://github.com/Finland93)
