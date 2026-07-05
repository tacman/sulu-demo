<h1 align="center">Sulu - Demo Website</h1>

This is the official **Sulu Demo**. It was created to show a simple implementation of an application made
with Sulu and explains the basic steps.

This project also runs here: [https://sulu.rocks](https://sulu.rocks)

For information about Sulu have a look at our Homepage:
[http://sulu.io/](http://sulu.io/)

Our documentation is available under:
[http://docs.sulu.io/](http://docs.sulu.io/)

<br/>
<p align="center">
    <img width="80%" src="https://sulu.io/uploads/media/800x@2x/01/251-sulu-demo.gif?v=1-0" alt="Sulu Demo Slideshow">
</p>
<br/>

## Used Extensions

### [SuluArticleBundle](https://github.com/sulu/SuluArticleBundle)

The SuluArticleBundle adds support for managing articles in Sulu. Articles can be used in a lot of different ways to manage unstructured data with an own URL in an admin-list.
Most of the features, which can be used in pages, can also be used on articles - like templates, versioning, drafting, publishing and automation.

### [SuluAutomationBundle](https://github.com/sulu/SuluAutomationBundle)

The SuluAutomationBundle provides a way to manages future tasks which can be scheduled for entities in the Sulu-Admin. For example schedule the publishing of a page to a specific datetime in the future.

To enable automated tasks use the command ``task:run`` manually in the terminal or in a cronjob. This tasks executes the
pending automation tasks (see [SuluAutomationBundle Installation Docs](https://github.com/sulu/SuluAutomationBundle/blob/master/Resources/doc/installation.md)).

### [SuluWebTwig](https://github.com/sulu/web-twig) and [SuluWebJS](https://github.com/sulu/web-js)

A collection of helpful twig extensions and a tiny js component mangaement library.

## Requirements

* PHP 8.0
    - json extension
    - xml extension
    - simplexml extension
    - gd or imagick extension (needed for image converts)
* MySQL or PostgreSQL Server
* Elasticsearch 7
* Composer
* NPM if you want to run npm tasks

## Installation

```bash
git clone git@github.com:sulu/sulu-demo.git
cd sulu-demo
composer install
```

### Configure required services

The demo requires a running **MySQL**  and **ElasticSearch** instance.

Configure your `DATABASE_URL` and `ELASTICSEARCH_HOST` in the `.env.local`  see `.env` as reference.

If you don't want to install the services yourself you can use the provided [docker-compose.yml](https://docs.docker.com/compose/install/)
to start this services inside an own container:

```bash
docker-compose up
```

### Install fixtures

Install the demo with all fixtures by running:

```bash
bin/console sulu:build dev
```

## Legacy content migration (JSON fixtures)

When this demo was upgraded from Sulu 2 (PHPCR-backed pages/articles/snippets) to
Sulu 3 (Doctrine-backed `Sulu\Article` / `Sulu\Page` / `Sulu\Content`), the old
`Sulu\Bundle\PageBundle`, `Sulu\Bundle\ArticleBundle` and
`Sulu\Bundle\DocumentManagerBundle` classes were removed entirely, which left
the original `App\DataFixtures\Document\DocumentFixture` fixture unable to run
(it referenced classes that no longer exist). Its content — the "Artists"
pages and blog articles — is preserved as plain data and re-imported through
Sulu 3's real content system instead.

### How it works

```
src/DataFixtures/Legacy/*.json   (versioned snapshot, hand-ported from the
        │                         old PHPCR fixture arrays)
        │  app:legacy-fixtures-jsonl
        ▼
data/*.json                      (working directory, gitignored, regenerated
        │                         on every run)
        │  app:import-jsonl
        ▼
Database (ar_articles, pa_pages, ...)
```

* **`bin/console app:legacy-fixtures-jsonl`** copies the versioned snapshot
  (`src/DataFixtures/Legacy/article.json`, `page.json`) into `data/`. This is
  a separate step so `data/` can be regenerated, inspected, or hand-edited
  without touching the checked-in source of truth. The dataset is small (a
  few dozen rows), so it's plain `json_decode()`/`json_encode()` — no
  streaming library needed. For a much larger dataset, reach for something
  like [`halaxa/json-machine`](https://github.com/halaxa/json-machine)
  (constant-memory JSON parsing) instead.

* **`bin/console app:import-jsonl`** reads `data/*.json` and populates the
  database by dispatching the **real** Sulu content messages — the same ones
  the admin UI uses — instead of writing to any bespoke entity:
  * `Sulu\Page\Application\Message\CreatePageMessage` /
    `ModifyPageMessage` / `ApplyWorkflowTransitionPageMessage` for the
    "Artists"/"Musiker" overview page and its 5 artist profile pages.
  * `Sulu\Article\Application\Message\CreateArticleMessage` /
    `ModifyArticleMessage` / `ApplyWorkflowTransitionArticleMessage` for the
    5 blog articles.

  Each entity is created once (first locale) and then modified for
  additional locales, then published, mirroring how a real editor would use
  the admin UI. Media (`headerImage`, excerpt images) and `albums` block
  references are resolved from filenames/titles in the JSON to real
  `Media`/`Album` IDs at import time via `App\Common\MediaLookup` and the
  `Album` repository.

### Usage

```bash
# 1. Load the base Sulu + album fixtures first (albums are referenced by title)
bin/console doctrine:fixtures:load

# 2. Regenerate data/*.json from the versioned snapshot
bin/console app:legacy-fixtures-jsonl

# 3. Import into the database via Sulu's content messages
bin/console app:import-jsonl
```

`app:import-jsonl` is meant to run once against a fresh webspace: re-running
it against already-imported data fails fast with a route-uniqueness
violation rather than silently creating duplicates.

## Usage

Now you can try out our demo, there is no need to configure a virtual host. Just use the build in web servers:

```bash
php -S 127.0.0.1:8000 -t public config/router.php
```

Then you can access the administration interface via [http://127.0.0.1:8000/admin](http://127.0.0.1:8000/admin). The default user and password is “admin”.

The web frontend can be found under [http://127.0.0.1:8000](http://127.0.0.1:8000).

## Tests

```bash
composer bootstrap-test-environment
composer lint
composer test
```

## Questions? We have answers!

We've got a [#Slack](https://sulu.io/community#chat) channel where you can talk directly to strategists, developers and designers.
