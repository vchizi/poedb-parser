# poedb-parser

## Item mods

`download_items_mods.php` downloads item mods from poedb and saves them to `data/all_mods.json`.

```
php download_items_mods.php [--debug] [item ...]
```

Download every item and rebuild `data/all_mods.json`:

```
php download_items_mods.php
```

Download only some items:

```
php download_items_mods.php Claws Amulets
php download_items_mods.php Jewellery "Body Armours(str)"
```

- An item is a subcategory from the `$items` list (`Claws`, `Amulets`, `Gloves(dex)`) or a whole
  category (`Weapons`, `Jewellery`, `Gloves`, `Maps`).
- Names are case-insensitive, and `_` and spaces are treated the same. Quote names that contain
  spaces or brackets.
- An unknown name stops the script and prints every available category and item.
- Only the mods of the downloaded pages are replaced in `data/all_mods.json`; everything else
  stays as it is, in the same order. If a page fails to download, its old mods are kept.

Inspect raw poedb data without touching `data/all_mods.json`:

```
php download_items_mods.php Claws --debug
```

- Saves each page's raw `ModsView` payload as JSON to `tmp/`.
- When a page can't be parsed, its HTML is saved to `tmp/` instead.
- Without item names, every page is fetched.
