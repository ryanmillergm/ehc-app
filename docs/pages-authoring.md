# Pages Authoring Guide

This guide explains how `PageTranslation` pages are authored and rendered.

## 1) Architecture Summary

Each page translation uses one explicit render mode:

- `template`: curated layout templates
- `blocks`: flexible block builder
- `custom`: full custom body HTML rendered inside app layout

Core types:

- `App\Enums\PageRenderMode`
- `App\Enums\PageTemplate`

Core runtime renderer:

- `App\Livewire\Pages\ShowPage`

Renderer blades:

- `resources/views/livewire/pages/renderers/template.blade.php`
- `resources/views/livewire/pages/renderers/blocks.blade.php`
- `resources/views/livewire/pages/renderers/custom.blade.php`

## 2) Data Model Reference

`page_translations` fields used by this system:

- `render_mode` (`template|blocks|custom`)
- `template` (`standard|campaign|story|immersive`)
- `theme`
- `hero_mode`
- `hero_style` (`contained|full_bleed`)
- `hero_height` (`70|80|100`)
- `hero_overlay` (`none|light|medium|dark`)
- `hero_text_align` (`left|center|right`)
- `hero_text_width` (`narrow|normal|wide`)
- `hero_title`
- `hero_subtitle`
- `hero_cta_text`
- `hero_cta_url`
- `content_blocks` (JSON)
- `custom_html` (longText)
- `custom_html_is_trusted` (boolean)

Legacy note:

- `layout_data` is no longer part of the active authoring flow.

## 3) Render Resolution and Fallbacks

`ShowPage` resolves rendering as:

1. Resolve active translation by slug/language rules.
2. Resolve SEO/canonical metadata.
3. Resolve render mode.
4. Dispatch to mode renderer.

Fallback behavior:

- Unknown `render_mode` -> `template`
- Unknown `template` -> `standard`
- Empty/invalid `content_blocks` -> fallback content view
- Empty `custom_html` -> renders empty custom container safely

## 4) Authoring Workflows

## Template Mode

Use when:

- you want a polished predefined layout
- non-technical editors need fast publishing

Template choices:

- `standard`: guidebook layout for evergreen information, service details, and general pages.
- `campaign`: action landing layout for donation, volunteer, and urgent response pages.
- `story`: longform journal layout for testimonies, updates, and reflective ministry stories.
- `immersive`: cinematic feature layout for media-led pages where the hero image or video should carry the first impression.

Editor flow:

1. Set `Render Mode` = `Template`.
2. Select one template (`standard`, `campaign`, `story`, `immersive`).
3. Configure hero/theme fields.
4. Publish and QA.

## Blocks Mode

Use when:

- you need layout flexibility without writing raw HTML
- marketing content is section-heavy

Editor flow:

1. Set `Render Mode` = `Block Builder`.
2. Add/reorder blocks in `Content Blocks`.
3. Populate each block’s fields.
4. Publish and QA.

Block-builder layout rules:

- Hero blocks manage their own contained/full-bleed layout.
- Standard content blocks render in a readable content column.
- Grid/card blocks render in a wider page container.
- Structured blocks use plus-button repeaters instead of JSON textareas.

Block hero media:

- `Hero Media = Header Image` uses the Page Translation image relationship with role `header`.
- `Hero Media = Hero Video` uses the Video Relationship with role `hero_video`.
- `Hero Media = Hero Slider` uses an Image Group Relationship with role `hero_slider`; if no active slider image exists, it falls back to the header image.
- Missing hero media renders the built-in gradient fallback.

Gallery blocks:

- Existing Image selects a reusable Images record.
- Upload Image stores the file like the Images resource and creates a reusable `images` row on save.
- Source URL renders the provided external image URL directly.

## Custom Mode

Use when:

- you need full-body handcrafted markup
- editor has HTML confidence

Editor flow:

1. Set `Render Mode` = `Custom HTML`.
2. Add `custom_html`.
3. Enable trusted mode only when explicitly required and permitted.
4. Publish and QA.

## 5) Security and Sanitization

Sanitization profiles:

- `cms_rich_text`
- `cms_custom_html_strict`
- `cms_custom_html_trusted`

Important rules:

- HTML is sanitized on save.
- Script tags are removed.
- Inline event handlers (e.g. `onclick`) are removed.
- `javascript:` URLs are stripped.
- Trusted mode is still script-blocked.

Permission gate:

- `pages.render_unsafe_html`

If user lacks this permission, `custom_html_is_trusted` is forced off on save.

## 6) Mode Selection Guidance

Choose `template` when:

- speed and visual consistency matter most

Choose `blocks` when:

- you need reusable sections and flexible composition

Choose `custom` when:

- page needs unique markup not practical in templates/blocks

## 7) QA Checklist (Before Publish)

Content:

- title/description are correct
- links and CTAs work
- layout behaves on desktop/mobile

SEO:

- canonical is correct
- title/description are present
- OG image works

Mode-specific:

- template: hero media fallback behaves correctly
- blocks: all blocks render and order is correct
- custom: sanitized output contains no scripts/event handlers

## 8) Troubleshooting Matrix

Issue: page looks blank

- verify `render_mode`
- verify required content fields for that mode
- verify translation is active/published

Issue: trusted toggle not visible

- check user has `pages.render_unsafe_html`

Issue: custom HTML stripped more than expected

- check strict vs trusted mode
- ensure markup is in allowed HTML profile

Issue: wrong template appears

- verify `template` value is valid (`standard|campaign|story|immersive`)
- unknown values fallback to `standard`

## 9) Related Docs

- `docs/pages-block-catalog.md`
- `docs/pages-rollout.md`
- `docs/route-seo.md`
- `docs/seo-production-checklist.md`
