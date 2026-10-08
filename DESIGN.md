---
name: AskOnce
description: A studio address book for collecting client material.
colors:
  paper: "#faf9f6"
  ink: "#242b27"
  muted: "#59635d"
  line: "#dedfd8"
  green: "#245844"
  wash: "#edf2eb"
  white: "#ffffff"
  field-border: "#aeb8ae"
  green-hover: "#173e2f"
  error: "#853824"
  error-wash: "#fae9e2"
typography:
  display:
    fontFamily: "Georgia, serif"
    fontSize: "clamp(48px, 7vw, 88px)"
    fontWeight: 400
    lineHeight: 1.15
    letterSpacing: "-0.03em"
  headline:
    fontFamily: "Georgia, serif"
    fontSize: "42px"
    fontWeight: 400
    lineHeight: 1.15
    letterSpacing: "-0.03em"
  body:
    fontFamily: "Instrument Sans, Segoe UI, sans-serif"
    fontSize: "15px"
    lineHeight: 1.6
  label:
    fontFamily: "Instrument Sans, Segoe UI, sans-serif"
    fontSize: "14px"
    fontWeight: 600
rounded:
  control: "6px"
  panel: "8px"
components:
  button-primary:
    backgroundColor: "{colors.green}"
    textColor: "{colors.white}"
    rounded: "{rounded.control}"
    padding: "11px 20px"
  button-primary-hover:
    backgroundColor: "{colors.green-hover}"
  input:
    backgroundColor: "{colors.white}"
    textColor: "{colors.ink}"
    rounded: "{rounded.control}"
    padding: "10px 12px"
---

# Design System: AskOnce

## Overview

**Creative North Star: "The Studio Address Book"**

Warm paper, dark ink and understated green give client collection the feel of a working ledger. Serif headings establish calm hierarchy; readable forms and flat ruled lists keep the material and next action prominent. The landing page carries the same identity through editorial type and a plainly identified example request.

**Key Characteristics:**
- Warm monochrome surfaces with one green accent.
- Ruled lists, aligned details and generous form spacing.
- Plain action labels, visible focus and nearby feedback.

## Colors

### Primary

Green marks primary actions, links, focus and client-facing business identity. Green wash groups sharing instructions, client notices and the landing example. The darker green is the primary button hover state.

### Neutral

Paper is the page canvas; ink carries headings and body text. Muted ink supports dates, help, secondary actions and statuses. Pale rules separate rows and sections. White fields use a stronger field border so inputs remain identifiable against paper.

Errors, overdue information and destructive actions use rust; error summaries pair it with the pale error wash. Status meaning is also written in text.

## Typography

Georgia provides regular-weight display and section headings. Instrument Sans, with Segoe UI and sans-serif fallbacks, carries forms, navigation, data and action labels.

Page headings use the headline token, reducing to 36px on small screens. Section headings are 28px with a 1.25 line height. Request-list titles and answer headings use the sans serif at 18px and weight 600. Helper text is 13px; metadata and status labels are 12px. Progress figures use tabular numerals. The landing display scales with viewport width; its introductory copy is 20px with a 1.5 line height.

## Layout

The centered header spans up to 1160px; main content up to 1060px, with 32px horizontal padding. Account forms use a 480px container, business and client-edit forms 560px, the builder 850px and the account-free client form 680px. Main content begins 64px below the header; working rows generally use 24px vertical spacing.

Desktop lists align details in columns. The builder pairs request details and item fields; the landing pairs its heading with introductory copy and its workflow with an example. Below 800px the header wraps navigation onto its own horizontally scrollable row, and landing columns stack. Below 640px list headings disappear, records become stacked details, builder fields stack and share controls wrap. Form labels remain above inputs. Main page padding reduces to 20px.

## Elevation & Depth

The interface uses no shadows. Rules, whitespace and green wash establish grouping. Lists remain on the paper canvas; tinted panels identify sharing guidance and notices rather than enclosing every record.

## Shapes

Buttons and fields have gently curved control corners. Notices use the same radius; the sharing panel uses the larger panel radius. The landing example has square edges. Thin horizontal rules define list rows, answer sections and footer boundaries.

## Components

- **Header:** an ink wordmark with a small green dot, muted navigation and a green underline for the current page. Account actions sit at the trailing edge.
- **Actions:** solid green primary buttons have a 46px minimum height. Secondary actions are text buttons; destructive actions use rust. Hover changes the primary background over 180ms. Reduced-motion preference removes transitions.
- **Fields:** white inputs and selects have a 46px minimum height, stronger borders and labeled help underneath. Textareas retain the same visual language. Green outlines identify keyboard and field focus.
- **Lists:** clients, requests, answers, reminder deliveries and notifications use flat rows with rules. Request progress uses a slim green bar with written completion information. Notification titles use serif section headings and provide a request link and an unread action.
- **Feedback:** success notices use green wash; error summaries use error wash and list the problems. Client saves show status beside the item. Empty states use concise text and the same ruled boundaries.
- **Settings:** business name and timezone form a narrow labeled column, followed by a ruled storage section.
- **Landing example:** a square green-wash block presents an explicitly labeled sample checklist, with aligned item and status text separated by rules.

## Do's and Don'ts

- Keep new surfaces within the paper, ink and green address-book identity.
- Use flat ruled lists for records and history; keep labels and actions explicit.
- Preserve visible keyboard focus and readable stacked layouts on small screens.
- Do not add decorative dashboards, fabricated activity, testimonials or customer proof.
- Do not turn every record into a raised card or use color alone to communicate status.
