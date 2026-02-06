# Project Skills and Rules

## UI/UX Standards

### Real-time Filtering
- **Search Filters**: Every page containing a data table MUST include a real-time `onkeyup` JavaScript search filter. This filter should target the primary identifier (usually product name or customer name).
- **Default State**: All filters (search, date ranges, etc.) MUST be cleared/empty by default when the page loads, unless explicitly requested otherwise by the user.

### Filtering Logic
- Table rendering should be handled by a JavaScript `renderTable` function that filters the local data array based on the current state of UI inputs.
- Always include a "CLEAR" or "Reset" button that empties all filter inputs and re-renders the table.
