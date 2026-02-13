/**
 * Sync Manager
 * Handles network detection, sync orchestration, and UI updates
 */

class SyncManager {
    constructor() {
        this.isOnline = navigator.onLine;
        this.isSyncing = false;
        this.syncInterval = null;
        this.statusCallbacks = [];
        this.csrfToken = null;
        
        // Bind methods
        this.handleOnline = this.handleOnline.bind(this);
        this.handleOffline = this.handleOffline.bind(this);
        this.sync = this.sync.bind(this);
        
        // Initialize
        this.init();
    }

    /**
     * Initialize the sync manager
     */
    init() {
        // Get CSRF token from meta tag or global variable
        this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || 
                         window.csrfToken || 
                         document.querySelector('input[name="csrf_token"]')?.value;

        // Listen for online/offline events
        window.addEventListener('online', this.handleOnline);
        window.addEventListener('offline', this.handleOffline);

        // Start periodic sync check (every 30 seconds)
        this.syncInterval = setInterval(() => {
            if (this.isOnline && !this.isSyncing) {
                this.checkAndSync();
            }
        }, 30000);

        // Initial sync check
        if (this.isOnline) {
            this.checkAndSync();
        }

        // Update UI
        this.updateConnectionStatus();
    }

    /**
     * Handle going online
     */
    handleOnline() {
        this.isOnline = true;
        this.updateConnectionStatus();
        this.showNotification('Connection restored. Syncing...', 'success');
        this.sync();
    }

    /**
     * Handle going offline
     */
    handleOffline() {
        this.isOnline = false;
        this.updateConnectionStatus();
        this.showNotification('You are offline. Changes will be saved locally.', 'warning');
    }

    /**
     * Update connection status UI
     */
    updateConnectionStatus() {
        const statusElements = document.querySelectorAll('.connection-status');
        statusElements.forEach(el => {
            if (this.isOnline) {
                el.classList.remove('offline');
                el.classList.add('online');
                el.innerHTML = '<i class="bi bi-wifi"></i> Online';
            } else {
                el.classList.remove('online');
                el.classList.add('offline');
                el.innerHTML = '<i class="bi bi-wifi-off"></i> Offline';
            }
        });

        // Notify subscribers
        this.notifyStatusChange({ type: 'connection', isOnline: this.isOnline });
    }

    /**
     * Check for pending operations and sync if needed
     */
    async checkAndSync() {
        const pendingCount = await offlineDB.getPendingCount();
        if (pendingCount > 0) {
            this.updatePendingCount(pendingCount);
            await this.sync();
        }
    }

    /**
     * Perform sync operation
     */
    async sync() {
        if (!this.isOnline || this.isSyncing) {
            return;
        }

        this.isSyncing = true;
        this.notifyStatusChange({ type: 'sync', isSyncing: true });

        try {
            // Get pending operations
            const pendingOps = await offlineDB.getPendingOperations();
            
            if (pendingOps.length === 0) {
                this.isSyncing = false;
                this.notifyStatusChange({ type: 'sync', isSyncing: false });
                this.updatePendingCount(0);
                return;
            }

            // Mark operations as syncing
            for (const op of pendingOps) {
                await offlineDB.updateOperationStatus(op.operation_id, 'syncing');
            }

            // Send to server
            const response = await fetch('api/sync.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': this.csrfToken || ''
                },
                body: JSON.stringify({ operations: pendingOps })
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                // Mark operations as completed
                for (const op of pendingOps) {
                    const serverResult = result.results.find(r => r.operation_id === op.operation_id);
                    if (serverResult && serverResult.success) {
                        await offlineDB.updateOperationStatus(op.operation_id, 'completed');
                    } else {
                        await offlineDB.updateOperationStatus(
                            op.operation_id, 
                            'failed', 
                            serverResult?.error || 'Unknown error'
                        );
                    }
                }

                // Update last sync time
                await offlineDB.setLastSyncTime(new Date().toISOString());

                // Refresh data from server
                await this.fetchServerData();

                // Update UI
                const remainingPending = await offlineDB.getPendingCount();
                this.updatePendingCount(remainingPending);

                if (remainingPending === 0) {
                    this.showNotification('All changes synced successfully!', 'success');
                } else {
                    this.showNotification(`Synced ${result.results.filter(r => r.success).length} items. ${remainingPending} pending.`, 'info');
                }
            } else {
                throw new Error(result.error || 'Sync failed');
            }
        } catch (error) {
            console.error('Sync error:', error);
            
            // Mark operations as failed
            const pendingOps = await offlineDB.getPendingOperations();
            for (const op of pendingOps) {
                await offlineDB.updateOperationStatus(op.operation_id, 'failed', error.message);
            }

            this.showNotification('Sync failed: ' + error.message, 'danger');
        } finally {
            this.isSyncing = false;
            this.notifyStatusChange({ type: 'sync', isSyncing: false });
        }
    }

    /**
     * Fetch data from server to update local cache
     */
    async fetchServerData() {
        try {
            const lastSync = await offlineDB.getLastSyncTime();
            
            const response = await fetch('api/fetch-data.php' + (lastSync ? `?since=${encodeURIComponent(lastSync)}` : ''), {
                headers: {
                    'X-CSRF-Token': this.csrfToken || ''
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            if (data.success) {
                // Cache exams
                if (data.exams) {
                    for (const exam of data.exams) {
                        await offlineDB.cacheExam(exam);
                    }
                }

                // Cache questions
                if (data.questions) {
                    for (const question of data.questions) {
                        await offlineDB.cacheQuestion(question);
                    }
                }
            }
        } catch (error) {
            console.error('Fetch data error:', error);
        }
    }

    /**
     * Queue an operation for sync
     */
    async queueOperation(operationType, tableName, data, recordId = null) {
        const operation = await offlineDB.addToSyncQueue(operationType, tableName, data, recordId);
        
        // Update pending count
        const pendingCount = await offlineDB.getPendingCount();
        this.updatePendingCount(pendingCount);

        // Try to sync immediately if online
        if (this.isOnline && !this.isSyncing) {
            this.sync();
        }

        return operation;
    }

    /**
     * Update pending count UI
     */
    updatePendingCount(count) {
        const countElements = document.querySelectorAll('.pending-sync-count');
        countElements.forEach(el => {
            el.textContent = count;
            el.style.display = count > 0 ? 'inline-block' : 'none';
        });

        // Show/hide sync button
        const syncButtons = document.querySelectorAll('.sync-now-btn');
        syncButtons.forEach(btn => {
            btn.style.display = count > 0 ? 'inline-block' : 'none';
        });
    }

    /**
     * Show notification to user
     */
    showNotification(message, type = 'info') {
        // Check if there's a notification container
        let container = document.getElementById('sync-notifications');
        
        if (!container) {
            container = document.createElement('div');
            container.id = 'sync-notifications';
            container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:9999;max-width:300px;';
            document.body.appendChild(container);
        }

        const notification = document.createElement('div');
        notification.className = `alert alert-${type} alert-dismissible fade show`;
        notification.style.cssText = 'margin-bottom:10px;box-shadow:0 4px 12px rgba(0,0,0,0.15);';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        container.appendChild(notification);

        // Auto dismiss after 5 seconds
        setTimeout(() => {
            notification.remove();
        }, 5000);
    }

    /**
     * Subscribe to status changes
     */
    onStatusChange(callback) {
        this.statusCallbacks.push(callback);
    }

    /**
     * Notify subscribers of status changes
     */
    notifyStatusChange(status) {
        this.statusCallbacks.forEach(callback => callback(status));
    }

    /**
     * Manual sync trigger
     */
    async manualSync() {
        if (!this.isOnline) {
            this.showNotification('Cannot sync while offline. Please check your connection.', 'warning');
            return;
        }

        if (this.isSyncing) {
            this.showNotification('Sync already in progress...', 'info');
            return;
        }

        await this.sync();
    }

    /**
     * Get sync status
     */
    getStatus() {
        return {
            isOnline: this.isOnline,
            isSyncing: this.isSyncing,
            csrfToken: this.csrfToken
        };
    }

    /**
     * Cleanup on destroy
     */
    destroy() {
        window.removeEventListener('online', this.handleOnline);
        window.removeEventListener('offline', this.handleOffline);
        
        if (this.syncInterval) {
            clearInterval(this.syncInterval);
        }
    }
}

// Create global instance
let syncManager;

if (typeof window !== 'undefined') {
    // Wait for offlineDB to be ready
    if (typeof offlineDB !== 'undefined') {
        syncManager = new SyncManager();
    } else {
        window.addEventListener('DOMContentLoaded', () => {
            syncManager = new SyncManager();
        });
    }
}

/**
 * Helper function to handle form submission with offline support
 */
async function handleOfflineFormSubmit(form, operationType, tableName, options = {}) {
    const formData = new FormData(form);
    const data = {};
    
    formData.forEach((value, key) => {
        if (data[key]) {
            if (!Array.isArray(data[key])) {
                data[key] = [data[key]];
            }
            data[key].push(value);
        } else {
            data[key] = value;
        }
    });

    // Add record ID if provided
    const recordId = options.recordId || data.id || null;

    try {
        // Queue the operation
        const operation = await offlineDB.addToSyncQueue(operationType, tableName, data, recordId);
        
        // Update UI
        const pendingCount = await offlineDB.getPendingCount();
        if (typeof syncManager !== 'undefined') {
            syncManager.updatePendingCount(pendingCount);
        }

        // Show success message
        const message = options.successMessage || 'Changes saved locally. Will sync when online.';
        
        if (typeof syncManager !== 'undefined') {
            if (syncManager.isOnline) {
                syncManager.showNotification('Changes saved and syncing...', 'success');
                syncManager.sync();
            } else {
                syncManager.showNotification(message, 'warning');
            }
        }

        // Call success callback if provided
        if (options.onSuccess) {
            options.onSuccess(operation);
        }

        return operation;
    } catch (error) {
        console.error('Failed to queue operation:', error);
        
        if (typeof syncManager !== 'undefined') {
            syncManager.showNotification('Failed to save changes. Please try again.', 'danger');
        }
        
        if (options.onError) {
            options.onError(error);
        }
        
        throw error;
    }
}

/**
 * Initialize offline form handling
 */
function initOfflineForm(formSelector, operationType, tableName, options = {}) {
    const form = document.querySelector(formSelector);
    if (!form) return;

    form.addEventListener('submit', async (e) => {
        e.preventDefault();
        
        // Disable submit button
        const submitBtn = form.querySelector('button[type="submit"]');
        if (submitBtn) {
            submitBtn.disabled = true;
            submitBtn.dataset.originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
        }

        try {
            await handleOfflineFormSubmit(form, operationType, tableName, {
                ...options,
                onSuccess: (operation) => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = submitBtn.dataset.originalText;
                    }
                    if (options.onSuccess) options.onSuccess(operation);
                },
                onError: (error) => {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = submitBtn.dataset.originalText;
                    }
                    if (options.onError) options.onError(error);
                }
            });
        } catch (error) {
            if (submitBtn) {
                submitBtn.disabled = false;
                submitBtn.innerHTML = submitBtn.dataset.originalText;
            }
        }
    });
}
