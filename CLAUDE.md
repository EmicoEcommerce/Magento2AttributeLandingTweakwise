# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Module Overview

`Tweakwise_AttributeLandingTweakwise` is a Magento 2 compatibility module bridging two packages:
- `emico/m2-attributelanding` — attribute-based landing pages (e.g. "red-pants" = category "pants" + color filter "red")
- `tweakwise/magento2-tweakwise` — Tweakwise navigator integration

It replaces the legacy package `emico/m2-attributelanding-tweakwise`. PHP namespace: `Tweakwise\AttributeLandingTweakwise`, sources in `src/`.

## Commands

```bash
# Run all quality checks (PHPStan + PHPCS) — also runs automatically on commit via GrumPHP
vendor/bin/grumphp run

# Static analysis only
vendor/bin/phpstan analyse

# Coding standards only
vendor/bin/phpcs

# Run all tests
vendor/bin/codecept run

# Run a single suite
vendor/bin/codecept run Unit
vendor/bin/codecept run Functional

# Run a single test file
vendor/bin/codecept run Unit tests/Unit/ExampleTest.php
```

## Architecture

### Core Flow

When a visitor lands on an attribute landing page, this module:
1. Applies the page's predefined filters to the Tweakwise navigation request (`TweakwiseFilterApplier`)
2. Optionally applies a Tweakwise filter/sort/builder template to the request
3. Hides the predefined filters from the active filter display so users don't see them as "selected" (`TweakwiseFilterHider`)
4. Adjusts all filter URLs so adding/removing filters stays on the landing page or correctly returns to the category page

### Key Classes

**`Model/FilterManager`** — Central service. Resolves which active filters belong to the landing page vs. user selections. Used by all URL plugins to build correct filter URLs.

**`Model/FilterApplier/TweakwiseFilterApplier`** — Registered into `AggregateFilterApplier` via `di.xml`. Translates landing page filter definitions into `addAttributeFilter()` calls on the Tweakwise `NavigationRequest`, and applies template IDs.

**`Model/FilterHider/TweakwiseFilterHider`** — Replaces `FilterHiderInterface` (preference in `di.xml`). Controls which filters are hidden from the layered navigation when on a landing page.

**`Model/AjaxResultInitializer/LandingPageInitializer`** — Registered in the Tweakwise Ajax navigation `initializerMap`. Sets up the landing page context (category, filters) for AJAX requests, loading layout handle `tweakwise_ajax_landingpage`.

### URL Plugins (the most complex part)

Three plugins work together to keep filter URLs correct on a landing page. The landing page has `hide_selected_filters` as a key flag:

- When `hide_selected_filters = true`: the landing page filters are invisible in the URL, so every filter URL must re-inject them.
- When `hide_selected_filters = false`: landing page filters are visible in the URL, so removing them should navigate back to the category.

| Plugin | Plugged class | Purpose |
|--------|---------------|---------|
| `UrlPlugin` | `Tweakwise\Magento2Tweakwise\Model\Catalog\Layer\Url` | Cross-link (select filter → land on matching landing page), remove filter → back to category, clear all → category |
| `PathSlugStrategyPlugin` | `PathSlugStrategy` | Rebuilds path-slug filter URLs excluding/including landing page filters |
| `QueryParameterStrategyPlugin` | `QueryParameterStrategy` | Same for query-parameter URL strategy |

### Admin UI (Facet/Attribute Picker)

The landing page form (`emico_attributelanding_page_form.xml`) adds Tweakwise-specific fields: filter rows (attribute + value dropdowns), and filter/sort/builder template selectors.

The attribute and value dropdowns are populated via AJAX controllers:
- `Controller/Adminhtml/Ajax/Facets` — returns available facets (URL keys + titles)
- `Controller/Adminhtml/Ajax/FacetAttributes` — returns values for a specific facet

Both controllers have two paths: the default Tweakwise navigator API, or the optional Tweakwise Backend API (when a token is configured under `Stores → Configuration → Catalog → Tweakwise → Attribute Landing Pages`).

**`ApiClient/BackendApiClient`** — Calls `https://navigator-api.tweakwise.com` using a separate backend token (`TWN-Authentication` header) in addition to the regular instance key. Results are cached for 10 minutes in Magento's cache.

### DI Wiring Summary (`etc/di.xml`)

- `TweakwiseFilterApplier` injected into `AggregateFilterApplier` under key `tweakwise`
- `TweakwiseFilterHider` set as preference for `FilterHiderInterface`
- `LandingPageInitializer` registered in `Tweakwise\Magento2Tweakwise\Controller\Ajax\Navigation::initializerMap` under key `landingpage`
- `LandingPageResolver` injected into `PathSlugStrategy::rewriteResolvers` to resolve landing page URL rewrites
- Virtual type `NavigationConfig\LandingPage` uses `LandingPageInputProvider` as its filter form input provider

## Commit Message Convention

This repo uses [semantic-release](https://github.com/semantic-release/semantic-release). Use conventional commit format (`feat:`, `fix:`, `chore:`, etc.) — the CI pipeline derives version bumps and release notes from commit messages.
