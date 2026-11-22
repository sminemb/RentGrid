---
name: RentGrid
description: Warm paper, pine ink, and peach actions for a vehicle rental counter.
colors:
  peach: "#f4c5ae"
  peach-hover: "#efb69a"
  peach-pressed: "#e9a889"
  rust-focus: "#8c3f26"
  red-error: "#a03427"
  pine-ink: "#192724"
  muted-ink: "#505b54"
  warm-ground: "#f5f2e9"
  counter-paper: "#fffdf8"
  inset-stock: "#edece2"
  paper-rule: "#d6d5c8"
  field-rule: "#8c9287"
  receipt-note: "#dcdfd3"
typography:
  display:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: "4.625rem"
    fontWeight: 800
    lineHeight: 0.99
    letterSpacing: "-.04em"
  headline:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: "3.5rem"
    fontWeight: 800
    lineHeight: 1.12
    letterSpacing: "-.035em"
  title:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: "1.625rem"
    fontWeight: 800
    lineHeight: 1.12
    letterSpacing: "-.035em"
  item-title:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: "1.125rem"
    fontWeight: 800
    lineHeight: 1.12
    letterSpacing: "-.035em"
  body:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: 1.55
  label:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: ".875rem"
    fontWeight: 800
    lineHeight: "1.25rem"
  receipt-total:
    fontFamily: "Manrope, system-ui, sans-serif"
    fontSize: "2.375rem"
    fontWeight: 800
    lineHeight: 1.12
    letterSpacing: "-.035em"
    fontFeature: '"tnum"'
  reference:
    fontFamily: "ui-monospace, Consolas, monospace"
    fontSize: "1.375rem"
    fontWeight: 700
    lineHeight: 1.35
rounded:
  control: ".5rem"
  panel: ".875rem"
  circle: "50%"
spacing:
  field-gap: ".4375rem"
  inline-gap: ".75rem"
  grid-gap: "1rem"
  panel-gap: "1.25rem"
  panel-inset: "1.75rem"
  shell-gutter: "2rem"
components:
  button-primary:
    backgroundColor: "{colors.peach}"
    textColor: "{colors.pine-ink}"
    rounded: "{rounded.control}"
    padding: ".625rem 1.125rem"
  button-primary-hover:
    backgroundColor: "{colors.peach-hover}"
  button-primary-active:
    backgroundColor: "{colors.peach-pressed}"
  button-primary-disabled:
    backgroundColor: "{colors.inset-stock}"
    textColor: "{colors.muted-ink}"
  button-secondary:
    backgroundColor: "{colors.counter-paper}"
    textColor: "{colors.pine-ink}"
    rounded: "{rounded.control}"
    padding: ".625rem 1.125rem"
  link-row-action:
    textColor: "{colors.pine-ink}"
    rounded: "{rounded.control}"
    padding: ".4375rem .75rem"
  link-back:
    textColor: "{colors.pine-ink}"
  field-input:
    backgroundColor: "{colors.counter-paper}"
    textColor: "{colors.pine-ink}"
    typography: "{typography.body}"
    rounded: "{rounded.control}"
    padding: ".625rem .75rem"
  panel-trip:
    backgroundColor: "{colors.counter-paper}"
    textColor: "{colors.pine-ink}"
    rounded: "{rounded.panel}"
    padding: "{spacing.panel-inset}"
  progress-current:
    backgroundColor: "{colors.pine-ink}"
    textColor: "{colors.counter-paper}"
    rounded: "{rounded.circle}"
    size: "1.375rem"
  receipt-quote:
    backgroundColor: "{colors.pine-ink}"
    textColor: "{colors.counter-paper}"
    rounded: "{rounded.panel}"
  receipt-confirmation:
    backgroundColor: "{colors.pine-ink}"
    textColor: "{colors.counter-paper}"
    rounded: "{rounded.panel}"
---

# Design System: RentGrid

## Overview

**Creative North Star: "Rental Counter"**

Warm stock and dark pine ink take their cues from a rental desk. Manrope and a few crisp rules make dates, vehicle labels, and actions easy to scan. The dark date form anchors the search opening and shares its palette with the booking receipts.

A dark receipt gives pricing and confirmation a recognizable place in the journey. The total is the largest line in the quote. The booking reference takes that position on confirmation, above the perforated rule and the amount due.

**Key Characteristics**

- Warm ground with flat paper panels
- Pine-black lettering and peach primary actions
- Compact, ruled fleet rows with outlined actions
- Inverted receipts with a perforated divider

## Colors

### Primary

- **Peach** fills the main action and marks the lead label on dark receipts. The hover and pressed variants deepen the fill.

### Secondary

- **Rust focus** outlines keyboard focus. **Red error** marks invalid fields and their messages.

### Neutral

- **Warm ground** frames the page. **Counter paper** carries forms, the header, fleet results, and detail panels.
- **Pine ink** carries headings and control labels, and becomes the receipt background and current step marker. **Muted ink** carries supporting copy on paper.
- **Inset stock** fills icon tiles, loading bars, and disabled controls. **Paper rule** separates surfaces and rows. **Field rule** strengthens interactive outlines and draws the receipt perforation.
- **Receipt note** keeps billing detail readable on pine ink.

**The Peach Action Rule.** Use the peach fill for the primary action. Give row actions and secondary controls a paper or transparent resting state.

## Typography

Manrope is self-hosted and sets the interface from display through field labels. Tight, heavy headings distinguish the task and vehicle names from the quieter body text. Dates and pricing use tabular figures. The selectable booking reference uses a monospace face so the characters are easy to read back.

| Role | Use |
| --- | --- |
| Display | The search opening |
| Headline | The review and confirmation titles |
| Title and item title | Panel headings and fleet row labels |
| Body and label | Supporting copy and persistent field labels |
| Receipt total and reference | The amount due on review and the booking code on confirmation |

**The Receipt Hierarchy Rule.** Put the quote total or confirmation reference above the perforation and keep itemized amounts aligned at the trailing edge.

## Layout

The centered shell caps at 72rem. Shared gutters narrow at 48rem and again at 23rem. Ruled lists align the vehicle icon, label, and action, allowing the label to wrap. At 23rem and below the action moves beneath the label. In review, the receipt sits beside the itinerary and contact form when there is room. At 48rem and below the order is itinerary, receipt, then contact form. At 30rem and below the contact fields stack and the submit action spans the form.

## Elevation & Depth

Paper panels use one-pixel rules and open space to define their edges. Inset stock distinguishes small icon tiles and loading placeholders. The receipt uses pine ink, a dashed rule, and round cutouts the color of the page ground at both ends of the rule.

**The Flat Paper Rule.** Define panels with tone, space, and a fine rule. Use the cutout and dashed line together for the receipt seam.

## Shapes

Panels have gently rounded, stock-like corners. Controls and icon tiles use the smaller radius. Step markers and receipt cutouts are circular. Icons use a consistent rounded outline stroke.

## Components

### Buttons and links

Peach buttons carry the search and booking actions. Paper secondary buttons carry retry. Vehicle row actions use a fine outline with a lightly filled hover state, while back links gain an underline on hover. Use the rust focus ring with a 2px outline and 4px offset. Color transitions run for 160ms with an ease-out curve and are removed when reduced motion is requested.

### Fields

Keep the label above the field. Use a paper fill, a strong outline, and the shared control radius. Hover darkens the outline. An invalid field pairs the red border with an attached text message.

### Panels and fleet rows

The trip and contact panels share paper fill, panel corners, and inset spacing. Keep the fleet in one ruled container. The vehicle icon, public label, model detail, and row action align for scanning.

### Booking progress

Small numbered circles and fine connecting rules show the order. Fill the current step with pine ink and signal it with `aria-current="step"`.

### Receipts

Use the same dark panel and perforation in review and confirmation. The quote pairs its lead total with a two-column breakdown. Confirmation puts the selectable code in the lead position and keeps the amount due in the detail row.

## Do's and Don'ts

- Do keep field labels and actionable error messages visible.
- Do keep vehicle labels and receipt values readable when they wrap.
- Do use the quiet paper row treatment for vehicle selection and keep the peach fill for the main action.
- Don't add drop shadows to paper panels or the receipt.
- Don't use monospace for body copy.
