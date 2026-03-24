# Design System Strategy: The Academic Atelier

## 1. Overview & Creative North Star
This design system moves away from the sterile, "boxed-in" feeling of traditional educational platforms. Our Creative North Star is **"The Academic Atelier."**

Like a high-end design studio or a modern university lounge, the interface should feel curated, spacious, and warm. We break the "template" look through **Intentional Asymmetry** and **Tonal Depth**. Instead of rigid grids and heavy borders, we use overlapping elements and high-contrast typography scales to guide the student’s eye. This creates an environment that is authoritative enough for professional study but approachable enough for daily creative exploration.

---

## 2. Color & Surface Philosophy
The palette is anchored in the tension between the heat of **Terracotta (#D04B17)** and the cooling clarity of **Secondary Red-Orange (#B16247)**.

### The "No-Line" Rule
Standard 1px borders are strictly prohibited for sectioning. Structural boundaries must be defined solely through background color shifts. To separate a sidebar from a main feed, transition from `surface` to `surface-container-low`.

### Surface Hierarchy & Nesting
Treat the UI as a series of physical layers—like heavy-weight vellum paper.
* **Base:** `surface` for the main canvas.
* **Content Blocks:** `surface-container` for primary content areas.
* **Elevated Focus:** `surface-container-highest` for high-priority interactive cards.
* **The "Glass & Gradient" Rule:** Use Glassmorphism for floating navigation or overlays. Apply `surface` at 80% opacity with a `backdrop-filter: blur(12px)`. For Hero CTAs, use a subtle linear gradient from `primary` to `primary_container` at a 135-degree angle to provide visual "soul."

---

## 3. Typography
We utilize a sophisticated dual-font pairing to balance academic authority with modern readability.

* **Display & Headlines (Manrope):** This is our "Editorial" voice. Use `display-lg` (3.5rem) with tight letter-spacing (-0.02em) for hero sections. Headlines should feel bold and intentional, using `on_surface` to maintain a grounded, professional weight.
* **Body & UI (Inter):** This is our "Functional" voice. Inter provides high legibility for long-form study material.
* `body-lg` (1rem) for primary reading.
* `label-md` (0.75rem) in `on_surface_variant` for metadata and micro-copy.
* **Hierarchy Tip:** Always skip a size in the scale to create dramatic contrast (e.g., pair a `headline-lg` title directly with `body-md` metadata).

---

## 4. Elevation & Depth
In this system, depth is a product of light and layering, not artificial lines.

* **The Layering Principle:** Achieve lift by "stacking" tiers. A `surface-container-lowest` card placed on a `surface-container-low` background creates a soft, natural lift that feels premium and tactile.
* **Ambient Shadows:** For floating elements (like Modals), use extra-diffused shadows.
* *Shadow Property:* `0px 20px 40px rgba(60, 45, 40, 0.06)` (a tint of our `inverse_surface`).
* **The "Ghost Border" Fallback:** If a border is required for accessibility, use `outline_variant` at **15% opacity**. Never use a 100% opaque border.

---

## 5. Components

### Buttons
* **Primary:** Solid `primary` with `on_primary` text. Use `rounded-md` (0.75rem) corner radius. On hover, transition to `primary_container`.
* **Secondary:** `secondary_container` background with `on_secondary_container` text. This provides a refreshing "Teal" break from the warmth.
* **Tertiary:** No background. Use `primary` text with a subtle `surface-container-high` hover state.

### Input Fields
* **Styling:** Use `surface-container-low` as the fill. Remove all borders except for a 2px bottom-weighted `outline` that animates to `primary` on focus.
* **Corner Radius:** Follow the `sm` (0.25rem) scale for a more precise, academic feel.

### Cards & Lists
* **The Divider Ban:** Never use horizontal lines. Use `spacing-8` (2rem) of vertical white space or a background shift to `surface-container-lowest` to separate list items.
* **Interactive Cards:** Use `rounded-xl` (1.5rem) to make large containers feel friendly and approachable.

### New Signature Component: "The Study Insight"
A specialized callout box using `tertiary_fixed` background with `tertiary` text. Use this for tips, "did you know" facts, or academic citations to provide a cool-toned visual "anchor" in a warm layout.

---

## 6. Do’s and Don’ts

### Do:
* **Embrace Asymmetry:** Offset images or text blocks by `spacing-10` to create a modern editorial feel.
* **Use Generous Leading:** Ensure `body-lg` has a line-height of at least 1.6 to prevent "academic stuffiness."
* **Tone-on-Tone:** Use `on_surface_variant` text on `surface_variant` backgrounds for low-priority information.

### Don’t:
* **Don't Use Pure Black:** Always use `on_background` for text to maintain the "Terracotta" warmth.
* **Don't Use Sharp Corners:** Avoid `none` (0px) rounding. Even our most "professional" elements should use at least `sm` (0.25rem) to remain student-friendly.
* **Don't Overuse the Primary Color:** The Terracotta is energetic; use it for CTAs and highlights. Use the `tertiary` and `secondary` scales to provide the "calm" necessary for a learning environment.