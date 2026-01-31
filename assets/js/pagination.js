/**
 * Pagination Component for POS-DEWAAN
 * Handles client-side pagination logic and UI generation.
 */

const Pagination = {
    /**
     * Renders pagination controls into a container.
     * @param {string} containerId - ID of the container element.
     * @param {number} totalItems - Total number of items to paginate.
     * @param {number} currentPage - Current active page (1-based).
     * @param {number} pageSize - Number of items per page.
     * @param {function} onPageChange - Callback function when page changes.
     */
    render: function(containerId, totalItems, currentPage, pageSize, onPageChange) {
        const container = document.getElementById(containerId);
        if (!container) return;

        const totalPages = Math.ceil(totalItems / pageSize);
        if (totalPages <= 1) {
            container.innerHTML = '';
            return;
        }

        let html = `
            <div class="flex items-center justify-between gap-4 mt-6 no-print">
                <div class="text-xs font-bold text-gray-400 uppercase tracking-widest">
                    Showing ${(currentPage - 1) * pageSize + 1} to ${Math.min(currentPage * pageSize, totalItems)} of ${totalItems} entries
                </div>
                <div class="flex items-center gap-1">
        `;

        // Previous Button
        html += `
            <button onclick="${onPageChange.name}(${currentPage - 1})" 
                    ${currentPage === 1 ? 'disabled' : ''} 
                    class="w-10 h-10 flex items-center justify-center rounded-xl border border-gray-100 bg-white text-gray-500 hover:bg-gray-50 transition active:scale-95 disabled:opacity-30 disabled:pointer-events-none shadow-sm">
                <i class="fas fa-chevron-left text-xs"></i>
            </button>
        `;

        // Page Numbers
        const range = 2; // Number of pages to show before and after current page
        for (let i = 1; i <= totalPages; i++) {
            if (i === 1 || i === totalPages || (i >= currentPage - range && i <= currentPage + range)) {
                html += `
                    <button onclick="${onPageChange.name}(${i})" 
                            class="w-10 h-10 flex items-center justify-center rounded-xl border ${i === currentPage ? 'bg-primary text-white border-primary shadow-lg shadow-teal-900/10' : 'bg-white text-gray-600 border-gray-100 hover:bg-gray-50'} font-bold text-xs transition active:scale-95 shadow-sm">
                        ${i}
                    </button>
                `;
            } else if (i === currentPage - range - 1 || i === currentPage + range + 1) {
                html += `<span class="px-2 text-gray-400">...</span>`;
            }
        }

        // Next Button
        html += `
            <button onclick="${onPageChange.name}(${currentPage + 1})" 
                    ${currentPage === totalPages ? 'disabled' : ''} 
                    class="w-10 h-10 flex items-center justify-center rounded-xl border border-gray-100 bg-white text-gray-500 hover:bg-gray-50 transition active:scale-95 disabled:opacity-30 disabled:pointer-events-none shadow-sm">
                <i class="fas fa-chevron-right text-xs"></i>
            </button>
        `;

        html += `
                </div>
            </div>
        `;

        container.innerHTML = html;
    },

    /**
     * Slices the data array for the current page.
     * @param {Array} data - The full data array.
     * @param {number} currentPage - Current active page (1-based).
     * @param {number} pageSize - Number of items per page.
     * @returns {Array} - The paginated data.
     */
    paginate: function(data, currentPage, pageSize) {
        const start = (currentPage - 1) * pageSize;
        const end = start + pageSize;
        return data.slice(start, end);
    }
};
