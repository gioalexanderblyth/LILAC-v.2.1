// MOU/MOA Bulk Operations JavaScript
document.addEventListener('DOMContentLoaded', function() {
    const bulkToolbar = document.getElementById('bulkOperationsToolbar');
    const selectedCount = document.getElementById('selectedCount');
    const selectAllBtn = document.getElementById('selectAllBtn');
    const selectNoneBtn = document.getElementById('selectNoneBtn');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const selectAllCheckbox = document.getElementById('selectAllCheckbox');
    const bulkDeleteModal = document.getElementById('bulkDeleteConfirmModal');
    const bulkDeleteCount = document.getElementById('bulkDeleteCount');
    const cancelBulkDeleteBtn = document.getElementById('cancelBulkDelete');
    const confirmBulkDeleteBtn = document.getElementById('confirmBulkDelete');

    // Check if bulk operations elements exist
    if (!bulkToolbar || !selectedCount || !selectAllBtn || !selectNoneBtn || !bulkDeleteBtn || !selectAllCheckbox) {
        console.log('Bulk operations elements not found. Bulk functionality disabled.');
        return;
    }

    let selectedItems = new Set();

    // Function to update bulk operations UI
    function updateBulkOperationsUI() {
        const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
        const count = checkedCheckboxes.length;
        selectedCount.textContent = count;

        if (count > 0) {
            bulkToolbar.classList.remove('hidden');
            if (bulkDeleteBtn) bulkDeleteBtn.disabled = false;
        } else {
            bulkToolbar.classList.add('hidden');
            if (bulkDeleteBtn) bulkDeleteBtn.disabled = true;
        }

        const allCheckboxes = document.querySelectorAll('.row-checkbox');
        const checkedCount = checkedCheckboxes.length;
        
        if (selectAllCheckbox) {
            if (checkedCount === 0) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = false;
            } else if (checkedCount === allCheckboxes.length) {
                selectAllCheckbox.indeterminate = false;
                selectAllCheckbox.checked = true;
            } else {
                selectAllCheckbox.indeterminate = true;
            }
        }
    }

    // Function to handle individual checkbox change
    function handleRowCheckboxChange(checkbox) {
        const id = parseInt(checkbox.dataset.id);
        if (checkbox.checked) {
            selectedItems.add(id);
        } else {
            selectedItems.delete(id);
        }
        updateBulkOperationsUI();
    }

    function addCheckboxEventListener(checkbox) {
        checkbox.addEventListener('change', function() {
            handleRowCheckboxChange(this);
        });
    }

    // Select all functionality
    selectAllBtn.addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = true;
            selectedItems.add(parseInt(checkbox.dataset.id));
        });
        updateBulkOperationsUI();
    });

    // Select all checkbox functionality
    selectAllCheckbox.addEventListener('change', function() {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = this.checked;
            if (this.checked) {
                selectedItems.add(parseInt(checkbox.dataset.id));
            } else {
                selectedItems.delete(parseInt(checkbox.dataset.id));
            }
        });
        updateBulkOperationsUI();
    });

    // Select none functionality
    selectNoneBtn.addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.row-checkbox');
        checkboxes.forEach(checkbox => {
            checkbox.checked = false;
        });
        selectedItems.clear();
        updateBulkOperationsUI();
    });

    // Bulk delete functionality
    bulkDeleteBtn.addEventListener('click', function() {
        const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
        if (checkedCheckboxes.length === 0) return;
        
        bulkDeleteCount.textContent = checkedCheckboxes.length;
        bulkDeleteModal.classList.remove('hidden');
    });

    // Cancel bulk delete
    cancelBulkDeleteBtn.addEventListener('click', function() {
        bulkDeleteModal.classList.add('hidden');
    });

    // Confirm bulk delete
    confirmBulkDeleteBtn.addEventListener('click', function() {
        try {
            const checkedCheckboxes = document.querySelectorAll('.row-checkbox:checked');
            const idsToDelete = Array.from(checkedCheckboxes).map(checkbox => parseInt(checkbox.dataset.id));
            
            // Remove from localStorage
            const STORAGE_KEY = 'mou_moa_entries';
            const entries = JSON.parse(localStorage.getItem(STORAGE_KEY) || '[]');
            
            // Filter out deleted entries
            const filteredEntries = entries.filter(entry => !idsToDelete.includes(entry.id));
            localStorage.setItem(STORAGE_KEY, JSON.stringify(filteredEntries));

            // Remove related notifications for each deleted entry
            if (typeof window.removeNotificationsForEntry === 'function') {
                idsToDelete.forEach(id => window.removeNotificationsForEntry(id));
            } else if (typeof removeNotificationsForEntry === 'function') {
                idsToDelete.forEach(id => removeNotificationsForEntry(id));
            }
            
            // Remove from DOM
            idsToDelete.forEach(id => {
                const checkbox = document.querySelector(`.row-checkbox[data-id="${id}"]`);
                if (checkbox) {
                    const row = checkbox.closest('tr');
                    if (row) {
                        row.remove();
                    }
                }
            });
            
            bulkDeleteModal.classList.add('hidden');
            
            // Update pagination info
            const totalEntries = filteredEntries.length;
            const startEntry = totalEntries > 0 ? 1 : 0;
            const endEntry = totalEntries;
            
            const paginationContainer = document.querySelector('.hidden.sm\\:flex-1.sm\\:flex.sm\\:items-center.sm\\:justify-between');
            if (paginationContainer) {
                const paginationText = paginationContainer.querySelector('div p');
                if (paginationText) {
                    paginationText.textContent = `Showing ${startEntry} to ${endEntry} of ${totalEntries} results`;
                }
            }
            
        } catch (error) {
            console.error('Error during bulk delete:', error);
            const errorToast = document.createElement('div');
            errorToast.className = 'fixed top-4 right-4 bg-red-500 text-white px-4 py-2 rounded-lg z-50';
            errorToast.textContent = 'Failed to delete selected entries: ' + error.message;
            document.body.appendChild(errorToast);
            setTimeout(() => errorToast.remove(), 5000);
        }
    });

    // Close modal when clicking outside
    bulkDeleteModal.addEventListener('click', function(e) {
        if (e.target === this) {
            this.classList.add('hidden');
        }
    });

    // Initialize checkboxes
    const checkboxes = document.querySelectorAll('.row-checkbox');
    checkboxes.forEach(addCheckboxEventListener);
    
    // Initial UI update
    updateBulkOperationsUI();
});
