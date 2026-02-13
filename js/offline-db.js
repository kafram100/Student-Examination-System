/**
 * Offline Database Manager
 * Handles IndexedDB operations for sync queue and local caching
 */

const DB_NAME = 'ExamSystemOfflineDB';
const DB_VERSION = 1;

const STORES = {
    SYNC_QUEUE: 'sync_queue',
    EXAMS_CACHE: 'exams_cache',
    QUESTIONS_CACHE: 'questions_cache',
    SYNC_METADATA: 'sync_metadata'
};

class OfflineDB {
    constructor() {
        this.db = null;
    }

    /**
     * Initialize the IndexedDB database
     */
    async init() {
        return new Promise((resolve, reject) => {
            const request = indexedDB.open(DB_NAME, DB_VERSION);

            request.onerror = () => reject(request.error);
            request.onsuccess = () => {
                this.db = request.result;
                resolve(this.db);
            };

            request.onupgradeneeded = (event) => {
                const db = event.target.result;

                // Sync queue store
                if (!db.objectStoreNames.contains(STORES.SYNC_QUEUE)) {
                    const syncStore = db.createObjectStore(STORES.SYNC_QUEUE, { keyPath: 'operation_id' });
                    syncStore.createIndex('status', 'status', { unique: false });
                    syncStore.createIndex('created_at', 'created_at', { unique: false });
                }

                // Exams cache store
                if (!db.objectStoreNames.contains(STORES.EXAMS_CACHE)) {
                    const examsStore = db.createObjectStore(STORES.EXAMS_CACHE, { keyPath: 'id' });
                    examsStore.createIndex('user_id', 'user_id', { unique: false });
                    examsStore.createIndex('updated_at', 'updated_at', { unique: false });
                }

                // Questions cache store
                if (!db.objectStoreNames.contains(STORES.QUESTIONS_CACHE)) {
                    const questionsStore = db.createObjectStore(STORES.QUESTIONS_CACHE, { keyPath: 'id' });
                    questionsStore.createIndex('exam_id', 'exam_id', { unique: false });
                    questionsStore.createIndex('updated_at', 'updated_at', { unique: false });
                }

                // Sync metadata store
                if (!db.objectStoreNames.contains(STORES.SYNC_METADATA)) {
                    db.createObjectStore(STORES.SYNC_METADATA, { keyPath: 'key' });
                }
            };
        });
    }

    /**
     * Generate a UUID for operation IDs
     */
    generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    /**
     * Add an operation to the sync queue
     */
    async addToSyncQueue(operationType, tableName, data, recordId = null) {
        await this.ensureInitialized();
        
        const operation = {
            operation_id: this.generateUUID(),
            operation_type: operationType,
            table_name: tableName,
            record_id: recordId,
            data: data,
            status: 'pending',
            retry_count: 0,
            error_message: null,
            created_at: new Date().toISOString(),
            synced_at: null
        };

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORES.SYNC_QUEUE], 'readwrite');
            const store = transaction.objectStore(STORES.SYNC_QUEUE);
            const request = store.add(operation);

            request.onsuccess = () => resolve(operation);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get all pending sync operations
     */
    async getPendingOperations() {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORES.SYNC_QUEUE], 'readonly');
            const store = transaction.objectStore(STORES.SYNC_QUEUE);
            const index = store.index('status');
            const request = index.getAll('pending');

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get all sync operations (for debugging)
     */
    async getAllOperations() {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORES.SYNC_QUEUE], 'readonly');
            const store = transaction.objectStore(STORES.SYNC_QUEUE);
            const request = store.getAll();

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Update operation status
     */
    async updateOperationStatus(operationId, status, errorMessage = null) {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORES.SYNC_QUEUE], 'readwrite');
            const store = transaction.objectStore(STORES.SYNC_QUEUE);
            const request = store.get(operationId);

            request.onsuccess = () => {
                const operation = request.result;
                if (operation) {
                    operation.status = status;
                    if (errorMessage) {
                        operation.error_message = errorMessage;
                    }
                    if (status === 'failed') {
                        operation.retry_count = (operation.retry_count || 0) + 1;
                    }
                    if (status === 'completed') {
                        operation.synced_at = new Date().toISOString();
                    }
                    store.put(operation);
                }
                resolve(operation);
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Remove completed operations older than a certain date
     */
    async cleanupCompletedOperations(daysToKeep = 7) {
        await this.ensureInitialized();
        
        const cutoffDate = new Date();
        cutoffDate.setDate(cutoffDate.getDate() - daysToKeep);

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORES.SYNC_QUEUE], 'readwrite');
            const store = transaction.objectStore(STORES.SYNC_QUEUE);
            const request = store.openCursor();
            let deletedCount = 0;

            request.onsuccess = (event) => {
                const cursor = event.target.result;
                if (cursor) {
                    const operation = cursor.value;
                    if (operation.status === 'completed' && 
                        new Date(operation.synced_at) < cutoffDate) {
                        cursor.delete();
                        deletedCount++;
                    }
                    cursor.continue();
                } else {
                    resolve(deletedCount);
                }
            };
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Cache an exam locally
     */
    async cacheExam(examData) {
        await this.ensureInitialized();
        
        const exam = {
            ...examData,
            updated_at: new Date().toISOString()
        };

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORES.EXAMS_CACHE], 'readwrite');
            const store = transaction.objectStore(STORES.EXAMS_CACHE);
            const request = store.put(exam);

            request.onsuccess = () => resolve(exam);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get cached exam by ID
     */
    async getCachedExam(examId) {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORES.EXAMS_CACHE], 'readonly');
            const store = transaction.objectStore(STORES.EXAMS_CACHE);
            const request = store.get(examId);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get all cached exams for a user
     */
    async getCachedExams(userId) {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORES.EXAMS_CACHE], 'readonly');
            const store = transaction.objectStore(STORES.EXAMS_CACHE);
            const index = store.index('user_id');
            const request = index.getAll(userId);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Cache a question locally
     */
    async cacheQuestion(questionData) {
        await this.ensureInitialized();
        
        const question = {
            ...questionData,
            updated_at: new Date().toISOString()
        };

        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORES.QUESTIONS_CACHE], 'readwrite');
            const store = transaction.objectStore(STORES.QUESTIONS_CACHE);
            const request = store.put(question);

            request.onsuccess = () => resolve(question);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get cached questions for an exam
     */
    async getCachedQuestions(examId) {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORES.QUESTIONS_CACHE], 'readonly');
            const store = transaction.objectStore(STORES.QUESTIONS_CACHE);
            const index = store.index('exam_id');
            const request = index.getAll(examId);

            request.onsuccess = () => resolve(request.result);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Set sync metadata
     */
    async setSyncMetadata(key, value) {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORES.SYNC_METADATA], 'readwrite');
            const store = transaction.objectStore(STORES.SYNC_METADATA);
            const request = store.put({ key, value, updated_at: new Date().toISOString() });

            request.onsuccess = () => resolve();
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get sync metadata
     */
    async getSyncMetadata(key) {
        await this.ensureInitialized();
        
        return new Promise((resolve, reject) => {
            const transaction = this.db.transaction([STORES.SYNC_METADATA], 'readonly');
            const store = transaction.objectStore(STORES.SYNC_METADATA);
            const request = store.get(key);

            request.onsuccess = () => resolve(request.result ? request.result.value : null);
            request.onerror = () => reject(request.error);
        });
    }

    /**
     * Get last sync timestamp
     */
    async getLastSyncTime() {
        return await this.getSyncMetadata('last_sync_at');
    }

    /**
     * Set last sync timestamp
     */
    async setLastSyncTime(timestamp) {
        await this.setSyncMetadata('last_sync_at', timestamp);
    }

    /**
     * Get count of pending operations
     */
    async getPendingCount() {
        const pending = await this.getPendingOperations();
        return pending.length;
    }

    /**
     * Clear all cached data (useful for logout)
     */
    async clearAllData() {
        await this.ensureInitialized();
        
        const stores = [STORES.SYNC_QUEUE, STORES.EXAMS_CACHE, STORES.QUESTIONS_CACHE, STORES.SYNC_METADATA];
        
        return Promise.all(stores.map(storeName => {
            return new Promise((resolve, reject) => {
                const transaction = this.db.transaction([storeName], 'readwrite');
                const store = transaction.objectStore(storeName);
                const request = store.clear();

                request.onsuccess = () => resolve();
                request.onerror = () => reject(request.error);
            });
        }));
    }

    /**
     * Ensure database is initialized
     */
    async ensureInitialized() {
        if (!this.db) {
            await this.init();
        }
    }
}

// Create global instance
const offlineDB = new OfflineDB();

// Initialize on load
if (typeof window !== 'undefined') {
    offlineDB.init().catch(console.error);
}
