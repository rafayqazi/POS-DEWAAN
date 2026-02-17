---
name: Project Development Standards
description: Mandatory UI, UX, and design patterns for the POS-DEWAAN project.
---

# Project Skills and Rules

## UI/UX Standards

### Real-time Filtering
- **Search Filters**: Every page containing a data table MUST include a real-time `onkeyup` JavaScript search filter. This filter should target the primary identifier (usually product name or customer name).
- **Default State**: All filters (search, date ranges, etc.) MUST be cleared/empty by default when the page loads, unless explicitly requested otherwise by the user.

### Filtering Logic
- Table rendering should be handled by a JavaScript `renderTable` function that filters the local data array based on the current state of UI inputs.
- Always include a "CLEAR" or "Reset" button that empties all filter inputs and re-renders the table.

### Design Consistency
- **Theme & Palette**: Every new page or component MUST strictly follow the project's established theme and color palette. 
- **Colors**: Use the primary Teal (`#0f766e`), secondary dark Teal (`#134e4a`), and accent Amber (`#f59e0b`) as defined in `header.php`.
- **Aesthetics**: Maintain the "Premium" look using rounded corners (`rounded-2xl` for cards, `rounded-xl` for buttons), subtle glassmorphism (`glass` class), and consistent spacing.

## Printing Standards

### Thermal Receipt Optimization
- **Device Support**: All receipt printing MUST be optimized for the **MP-300 Smart Bluetooth Printer** (80mm width).
- **Dynamic Height**: PDF generation for receipts MUST use dynamic height calculation based on content to eliminate excessive white space at the bottom. The standard width is 80mm.
- **Footer Styling**: Developer information in the footer should be minimal (8px) and positioned at the very bottom with sufficient spacing from the receipt content.
