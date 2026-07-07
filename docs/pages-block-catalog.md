# Pages Block Catalog

This catalog documents supported `content_blocks` block types for `render_mode=blocks`.

## 1) hero

Use case:

- top-level section heading + CTA cluster

Fields:

- `eyebrow`
- `heading`
- `subheading`
- `hero_mode` (`none|image|video|slider`)
- `hero_style` (`contained|full_bleed`)
- `hero_height` (`70|80|100`)
- `hero_overlay` (`none|light|medium|dark`)
- `hero_text_align` (`left|center|right`)
- `hero_text_width` (`narrow|normal|wide`)
- `primary_cta_text`
- `primary_cta_url`
- `secondary_cta_text`
- `secondary_cta_url`

Media rules:

- `image` uses the page translation Header image relationship.
- `video` uses the page translation Hero Video relationship.
- `slider` uses the first active Hero Slider image group item, falling back to the Header image when the slider has no active images.
- Missing media renders the gradient fallback.

## 2) rich_text

Use case:

- long-form body content

Fields:

- `body` (rich HTML)

## 3) image

Use case:

- single image with optional caption

Fields:

- `src`
- `alt`
- `caption`

## 4) gallery

Use case:

- image grid

Fields:

- `items` repeater
- item `source_type` (`existing|upload|url`)
- item `image_id` for existing Images records
- item `upload_path` for uploaded Images files; uploads create reusable `images` records on save
- item `src` for direct source URLs
- item `title`
- item `alt`
- item `caption`

## 5) cta

Use case:

- conversion panel

Fields:

- `title`
- `body`
- `button_text`
- `button_url`

## 6) stats

Use case:

- KPI/impact metrics

Fields:

- `items` repeater
- item `label`
- item `value`

## 7) faq_teaser

Use case:

- short FAQ preview with link

Fields:

- `title`
- `body`
- `button_text`
- `button_url`

## 8) divider

Use case:

- visual spacing or line separator

Fields:

- `style` (`line|space`)

## 9) quote

Use case:

- pull quote/testimonial

Fields:

- `quote`
- `attribution`

## 10) testimonials

Use case:

- testimonial cards

Fields:

- `items` repeater
- item `quote`
- item `name`

## 11) timeline

Use case:

- sequence of milestones

Fields:

- `items` repeater
- item `title`
- item `body`

## 12) pricing

Use case:

- tiered giving/program options

Fields:

- `items` repeater
- item `name`
- item `price`
- item `features` nested repeater
- feature `text`

## 13) embed

Use case:

- embed external content

Fields:

- `url`
- `caption`

## 14) feature_grid

Use case:

- feature cards

Fields:

- `items` repeater
- item `title`
- item `body`

## 15) icon_list

Use case:

- checklist/bulleted highlights

Fields:

- `items` repeater
- item `text`

## 16) video

Use case:

- video embed section

Fields:

- `url`
- `caption`

## Validation and Sanitization Notes

- All string fields are sanitized on save.
- Scripts/event handlers are stripped.
- Invalid block payloads may render empty content.

## Related Docs

- `docs/pages-authoring.md`
- `resources/views/livewire/pages/blocks/`
