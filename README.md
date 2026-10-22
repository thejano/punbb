# PanBB 2

PanBB is a fast, lightweight discussion board written in PHP, based on PunBB 1.4 with full Kurdish (Sorani) language support and a right-to-left theme. Version 2 brings the codebase to modern PHP and MySQL.

## What's new in 2.0

- Runs on PHP 8.3, 8.4 and 8.5.
- Works with MySQL 8 and 9 (reserved words, utf8mb4, emoji in posts).
- PostgreSQL layer fixed for PHP 8.1+.
- English is the default language and Oxygen the default style for new installs. Kurdish and the Oxygen-RTL style are included and can be selected per user or set as the board default.
- Dropped the legacy `mysql` extension and SQLite 2 layers. Existing configs that use `mysql` or `mysql_innodb` fall back to mysqli automatically.

See `ChangeLog` for the full list.

## Requirements

- PHP 8.3 or newer with the `mbstring` extension.
- One of:
  - MySQL 5.7 or newer (MySQL 8 and 9 supported) with the `mysqli` extension.
  - PostgreSQL with the `pgsql` extension.
  - SQLite 3 with the `sqlite3` extension.
- Any web server that can run PHP (Apache, Nginx, Caddy, or the PHP built-in server for local testing).

## Installation

1. Clone the repository or download and extract the archive into your web root:

   ```
   git clone https://github.com/kardoqadir/PanBB-Kurdish-1.4.5.git panbb
   ```

2. Make sure the web server can write to `cache/`, `img/avatars/` and `extensions/`.

3. Create an empty database (for MySQL, use the `utf8mb4` character set).

4. Open `admin/install.php` in your browser and follow the steps. The installer writes `config.php` for you.

5. Delete or protect `admin/install.php` afterwards.

New installations use the LightOxygen style by default. It has a sidebar layout and a wide layout, and visitors can switch between them.

To configure the style, install the `lightoxygen_settings` extension under Administration → Extensions. Its page (Extensions → LightOxygen theme) sets the default layout and look, whether visitors may switch them, the colour scheme, accent colour and font, the index hero (who sees it, its heading, tagline and background image), the logo and favicon, category colours, the statistics block, the activity refresh interval and how many recent topics the index activity panel lists, whether the index sidebar repeats the Browse block, a footer line, social links and custom CSS. Uploads are stored in `img/lightoxygen/`, which the web server must be able to write. Without the extension, or while it is disabled, the style uses its built-in defaults.

### Local testing

```
php -S 127.0.0.1:8080
```

Then open http://127.0.0.1:8080/admin/install.php. SQLite 3 needs no database server: enter a file name such as `forum.sqlite3` as the database name.

## Upgrading from PanBB 1.4 / PunBB 1.4

1. Back up your files and database.
2. Turn on maintenance mode and disable all extensions from the admin panel.
3. Overwrite the old files with the new ones. Keep your `config.php`.
4. Clear the `cache/` directory.
5. Open `admin/db_update.php` in your browser.
6. Re-enable your extensions and turn maintenance mode off.
7. To switch an existing forum to the LightOxygen style, run these two SQL statements (replace `<prefix>` with your table prefix):

   ```
   UPDATE <prefix>config SET conf_value='LightOxygen' WHERE conf_name='o_default_style';
   UPDATE <prefix>users SET style='LightOxygen';
   ```

Existing MySQL tables keep their `utf8` character set. To store emoji in an existing forum, convert the tables to `utf8mb4`.

## Extensions

1. Extract the extension into the `extensions/` directory.
2. In the admin panel, open Extensions, then Install extensions.
3. Click Install next to the extension.

Extensions written for PunBB 1.4 generally work, but check them on PHP 8 before using them on a live forum.

## Theme development

The LightOxygen style lives in `style/LightOxygen`.

```
cd style/LightOxygen && npm install && npm run dev
npm run build
```

`dist/` is committed, so a forum does not need Node to run the style. Run `npm run build` and commit `dist/` after changing the sources. `style/LightOxygen/test/check.sh` checks a running forum (admin / secret123 must exist).

## Maintainer

PanBB is maintained by Dr. Pshtiwan at [Webchin](https://www.webchin.org).

Support forum: https://www.webchin.org/Meko/viewforum.php?id=37

## License

PanBB is free software released under the GNU General Public License, version 2 or later. See `COPYING`.

Copyright (C) 2002-2012 PunBB. Partially based on code (C) 2008-2009 FluxBB.org. Kurdish localisation and PanBB changes (C) 2015-2026 Webchin.
