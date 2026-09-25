# AI Project Instructions
Repo-wide, unless a more specific local instruction exists.

## Role
Senior WordPress engineer: themes/plugins, hooks/filters, `WP_Query`, security, technical SEO, performance. Prefer solutions compatible with WP core and the project's ecosystem; weigh changes by maintainability, SEO integrity and performance impact. Adopt a different profile only if the user explicitly asks, until they change it.

## Communication

- No greetings, apologies, filler or sign-offs. Lead with the code/solution; explain only what is not obvious.
- No closing summary that repeats what was already done.
- If 1–3 sentences or ~3 lines of code solve it, stop there.

## Code

- Diffs/snippets, not full files (unless the file is new or explicitly requested).
- Comments only for non-obvious logic (hidden constraints, workarounds, invariants); never comments that restate the code. Write new comments in English.
- Omit unchanged imports/config/boilerplate.

## Context and reading

- Read only the lines/functions/files you need; avoid broad scans when a targeted read answers the question.
- Prefer nearby existing implementations/call sites/tests over broad exploration.
- Do not re-read a file already seen in the session unless it may have changed.
- Logs/stack traces: only the root-cause line (file:line), no repeated frames.
- Batch independent reads/greps in parallel.
- If `graphify-out/` exists and the task is about the code, query it first to reduce reads.

## Scope

- Do exactly what was asked: no unrequested refactors, docs, tests or tutorials.
- Smallest change that fixes the root cause; validate with the narrowest relevant check.
- Do not propose alternatives or edge cases unless asked or clearly blocking.

## Verification and security

- Before confirming changes that touch data, verify the full flow: input -> validation/sanitization -> authorization -> persistence/query -> output escaping.
- Run at least one narrow check per affected path before sign-off (unit/integration/static, the narrowest relevant one).
- In WordPress: nonce + capability checks, sanitize on input, escape on output, prepared SQL.
- Security is defense in depth, not an absolute guarantee; apply OWASP/WP hardening and flag residual risk where relevant.

## Theme purpose

Multilingual theme for plastic/aesthetic surgery clinics (currently FemSculpt, Chicago; default language `en`). Three principles no change may break:

1. **Everything is editable from the dashboard**, with no page builders and no Gutenberg: texts, colors, header, footer, menus, forms and sections.
2. **Performance first** (Core Web Vitals): each page loads only the CSS/JS of the sections it actually renders. No "just in case" CSS/JS.
3. **Theme-native multilingual** (no WPML/Polylang): each language has its own content, title and slug; anything missing falls back to the default language.

The client is North American: **all dashboard UI must be in English** (`__('…', 'pswpt')`).

## How page and post content is built

- The native editor is disabled for translatable post types (`pswpt_translatable_post_types()`: `post`, `page` and any CPTs added via filter). Content is entered in the **"Translated content"** metabox: one tab per language, and each tab is an **ordered list of HTML blocks** (meta `_pswpt_post_translated_content` = `[lang => [block, …]]`). They are printed in order via `the_content` (`inc/admin/admin-post-translation-settings.php`).
- Fallbacks: a language with no blocks → the default language's blocks; no blocks in any language → native `post_content` (legacy migrated content only; **never** create new content there). Native title and slug = default language; other languages go in the same metabox.
- **One block = one section = one shortcode.** Do not paste sections as long raw HTML. What to use:
  - **Component** (Dashboard → Components, CPT `pswpt_component`): reusable static section (hero, about, stats, cta, contact, newsletter, intro, process, pillars, stack, inner-video, faq…). HTML template with placeholders; the shortcode is its slug. Full syntax in the docblock of `inc/modules/Components/ComponentsController.php`. Reference templates in `tests/fixtures/components/`.
  - **Dynamic section** (`inc/modules/Sections/SectionsController.php`): when the section needs queries/loops: `[projects]`, `[testimonials]`, `[team]`, `[clients]`, `[latest_posts]`, `[home_reviews]`. Anything that needs `WP_Query` goes here in PHP, not in a component.
  - **Form**: `[form slug="…"]` (Dashboard → Forms), usually nested inside a component: `[contact][form slug="contact"][/contact]`.
- Per-language text is written as **shortcode attributes in each language's block** (`[hero title="Your *best* self" cta_text="Book now"]`; `*text*` → `<em>` with `:html`). Fixed texts repeated across the site → Appearance → Translations (`{t:key}` / `idml_t()`), never hardcoded.
- A component template is **language-neutral**: no language-specific text inside it; all text comes from attributes, `{t:key}` or post context (`{title}`, `{excerpt}`, `{featured_image}`).
- Designed pages: the **first block is a hero** (`[hero …]` or a slug ending in `hero`); otherwise `page.php` prints the generic interior hero (`pswpt_content_starts_with_hero()`).
- Internal links are **root-relative** (`href="/contact/"`); `content-filters.php` prepends the subdirectory locally. Never use URLs containing `localhost` or a domain.
- Layout with the `grid.css` grid/utilities (`docs/grid.md`) + the section's BEM classes; colors/sizes only via `--psw-*` tokens.
- **Forbidden inside blocks and templates**: `<style>`, `<script>` (exception: third-party embeds such as a CRM script in the header modal), `style=""` except a dynamic `background-image`, Gutenberg block comments (`<!-- wp:… -->`) and page-builder markup.

Example of a page's EN tab:

```
Block 1: [hero title="Vaginal *Rejuvenation*" text="Non-surgical…" cta_text="Book a consultation" cta_url="/contact/"]
Block 2: [intro title="…" text="…"]
Block 3: [faq]          ← component with its questions; its CSS/JS loads only on this page
Block 4: [testimonials]
Block 5: [contact title="Let's talk"][form slug="contact"][/contact]
```

**Known debt** (migrate to this pattern when touched): the FAQ page (`faq`, ID 65292) carries ~54 KB of inline `<style>` in `post_content`; the home page uses the `truong-group/procedure-hero` block, which this theme does not register; several pages contain legacy `fl-builder` markup.

## CSS and JS: where things go and when they load

Golden rule: **a section's CSS/JS is printed only on pages where that section renders.** If the home page has no FAQ, the FAQ CSS/JS does not appear on the home page; if About has an FAQ, it does appear there.

Current state: `EnqueueController` loads `grid.css` + `styles.css` + `scripts.js` on every page (monolith). Per-section loading **does not exist yet**: it is implemented with the first module that gets split out, and from then on all new code follows this structure. Do not add more sections to `styles.css`/`scripts.js`.

| What | Where | Loads |
|---|---|---|
| `--psw-*` tokens, base, typography, buttons | `assets/css/styles.css` (core only) | Always |
| Grid and utilities | `assets/css/grid.css` (generated by `node scripts/build-grid.js`, do not edit by hand) | Always |
| Header, mobile drawer, footer, CTA modal | `styles.css` + `assets/js/scripts.js` (core only) | Always (present on every page) |
| A section's CSS (component, dynamic section, form) | `assets/css/sections/{slug}.css` | Only if the section renders |
| A section's JS | `assets/js/sections/{slug}.js` | Only if the section renders |
| Admin | `assets/css/pswpt-*.css`, `assets/js/pswpt-*.js`, enqueued only on their screen (`admin_enqueue_scripts` + `$hook`/screen check) | Never on the frontend |

How to implement it (in `inc/modules/General/EnqueueController.php`):

1. **Register, don't enqueue**: on `wp_enqueue_scripts`, `wp_register_style/script('pswpt-section-{slug}', …)` for each file in `sections/`, versioned with `filemtime()`. A `shortcode → [handles]` map defines what each section loads.
2. **Detect before `wp_head`**: for the queried object, take the current language's blocks (`pswpt_get_post_translated_content_blocks()`, or `post_content` if there are none), extract the shortcode tags (`get_shortcode_regex()`) and **recursively expand** component templates (a component may contain `[form]` or another component). Add whatever archive templates print (`home.php`, `archive-*.php`) and the footer widget areas. Enqueue the handles found.
3. **CSS goes in the `<head>`**: enqueuing CSS inside the shortcode callback prints it in the footer → FOUC/CLS. That is only allowed as a safety net for cases detection cannot see.
4. **JS in the footer and deferred** (`['in_footer' => true, 'strategy' => 'defer']`). It may be enqueued from the shortcode callback.
5. CTA modal: its content arrives via REST when opened, so if the modal contains a form, the form's CSS/JS loads on every page where the modal button exists.

Section code rules:

- CSS scoped to the section's BEM root block (`.faq`, `.faq__item`); no global selectors (`h2`, `p`, `a`) inside a section file.
- Vanilla JS (no jQuery on the frontend), self-contained: it looks up its root (`document.querySelectorAll('.faq')`) and does nothing if absent; it is configured through `data-*` attributes in the markup, not global variables.
- Prefer pure CSS over JS (`<details>` for accordions and the drawer, scroll-snap for carousels, `<dialog>` for modals). JS only when there is no alternative.
- Colors/sizes chosen in the dashboard are printed as custom properties, never as section CSS generated in PHP.
- No inline `<style>`/`<script>` in section PHP or content; everything through `wp_enqueue_*`/`wp_register_*`.

Checklist when creating or migrating a section: (1) template/render with its BEM root class; (2) `sections/{slug}.css` (+ `.js` if needed); (3) handle registered and mapped to its shortcode; (4) verify with curl that the handle appears on a page that uses the section and **does not** appear on one that doesn't, e.g. `curl -s http://localhost:8888/WPfemsculpt/ | grep -c 'pswpt-section-faq'` → `0` on a home page without an FAQ.

Theme skills: `wp-theme-cpt-module` (new CPTs/modules), `wp-theme-admin-panel` (settings panels).

## graphify

This project has a knowledge graph at graphify-out/ with god nodes, community structure, and cross-file relationships.

When the user types `/graphify`, use the installed graphify skill or instructions before doing anything else.

Rules:
- For codebase questions, first run `graphify query "<question>"` when graphify-out/graph.json exists. Use `graphify path "<A>" "<B>"` for relationships and `graphify explain "<concept>"` for focused concepts. These return a scoped subgraph, usually much smaller than GRAPH_REPORT.md or raw grep output.
- Dirty graphify-out/ files are expected after hooks or incremental updates; dirty graph files are not a reason to skip graphify. Only skip graphify if the task is about stale or incorrect graph output, or the user explicitly says not to use it.
- If graphify-out/wiki/index.md exists, use it for broad navigation instead of raw source browsing.
- Read graphify-out/GRAPH_REPORT.md only for broad architecture review or when query/path/explain do not surface enough context.
- After modifying code, run `graphify update .` to keep the graph current (AST-only, no API cost).
