/**
 * System Diagnostics Module
 * Handles the system diagnostics modal and test execution
 */

import MicroModal from 'micromodal';

/**
 * Initialize System Diagnostics
 */
export function initSystemDiagnostics() {
    jQuery(document).ready(function($) {
        const $button = $('#anar-system-diagnostics');
        const $modal = $('#anar-system-diagnostics-modal');
        const $testsList = $('#anar-diagnostics-tests-list');
        const $summary = $('#anar-diagnostics-summary');
        const $summaryContent = $('#anar-diagnostics-summary-content');
        const $loading = $('#anar-diagnostics-loading');
        const $error = $('#anar-diagnostics-error');
        const $rerunButton = $('#anar-diagnostics-rerun');

        let tests = [];
        let testResults = [];

        /**
         * Escape HTML to prevent XSS
         */
        function escapeHtml(text) {
            if (!text) return '';
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        /**
         * Get test list from server
         */
        function getTestList() {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: awca_ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'anar_run_system_diagnostics',
                        nonce: awca_ajax_object.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data.tests) {
                            resolve(response.data.tests);
                        } else {
                            reject(new Error(response.data?.message || 'Failed to get test list'));
                        }
                    },
                    error: function(xhr, status, error) {
                        reject(new Error('Network error: ' + error));
                    }
                });
            });
        }

        /**
         * Run a single test
         */
        function runTest(testId) {
            return new Promise((resolve, reject) => {
                $.ajax({
                    url: awca_ajax_object.ajax_url,
                    type: 'POST',
                    data: {
                        action: 'anar_run_system_diagnostics',
                        test_id: testId,
                        nonce: awca_ajax_object.nonce
                    },
                    success: function(response) {
                        if (response.success && response.data) {
                            resolve(response.data);
                        } else {
                            reject(new Error(response.data?.message || 'Test failed'));
                        }
                    },
                    error: function(xhr, status, error) {
                        reject(new Error('Network error: ' + error));
                    }
                });
            });
        }

        /**
         * Render test list
         */
        function renderTestList(testList) {
            $testsList.empty();
            tests = testList;
            testResults = [];

            testList.forEach((test, index) => {
                const $testItem = $('<div>', {
                    class: 'anar-diagnostics-test-item anar-diagnostics-test-pending',
                    'data-test-id': test.test_id
                });

                $testItem.html(`
                    <div class="anar-diagnostics-test-header">
                        <span class="anar-diagnostics-test-icon">
                            <span class="spinner is-active" style="display: none;"></span>
                            <span class="anar-diagnostics-test-check" style="display: none;">✓</span>
                            <span class="anar-diagnostics-test-cross" style="display: none;">✗</span>
                        </span>
                        <span class="anar-diagnostics-test-name">${escapeHtml(test.name)}</span>
                    </div>
                    <div class="anar-diagnostics-test-message" style="display: none;"></div>
                `);

                $testsList.append($testItem);
            });
        }

        /**
         * Update test status
         */
        function updateTestStatus(testId, result) {
            const $testItem = $testsList.find(`[data-test-id="${testId}"]`);
            const $icon = $testItem.find('.anar-diagnostics-test-icon');
            const $spinner = $icon.find('.spinner');
            const $check = $icon.find('.anar-diagnostics-test-check');
            const $cross = $icon.find('.anar-diagnostics-test-cross');
            const $message = $testItem.find('.anar-diagnostics-test-message');

            // Remove pending class
            $testItem.removeClass('anar-diagnostics-test-pending');

            if (result.passed) {
                // Success state
                $testItem.addClass('anar-diagnostics-test-success');
                $spinner.hide();
                $check.show();
                $message.html(`<span style="color: #46b450;">${escapeHtml(result.message)}</span>`).show();
            } else {
                // Error state
                $testItem.addClass('anar-diagnostics-test-error');
                $spinner.hide();
                $cross.show();
                $message.html(`<span style="color: #dc3232;">${escapeHtml(result.message)}</span>`).show();
            }

            // Store result
            testResults.push(result);
        }

        /**
         * Show loading state for a test
         */
        function showTestLoading(testId) {
            const $testItem = $testsList.find(`[data-test-id="${testId}"]`);
            const $icon = $testItem.find('.anar-diagnostics-test-icon');
            const $spinner = $icon.find('.spinner');
            const $check = $icon.find('.anar-diagnostics-test-check');
            const $cross = $icon.find('.anar-diagnostics-test-cross');

            $testItem.addClass('anar-diagnostics-test-pending');
            $spinner.show();
            $check.hide();
            $cross.hide();
        }

        /**
         * Run all tests sequentially
         */
        async function runAllTests() {
            $loading.show();
            $error.hide();
            $summary.hide();
            $rerunButton.hide();
            testResults = [];

            try {
                // Get test list
                const testList = await getTestList();
                renderTestList(testList);

                // Run each test sequentially
                for (let i = 0; i < testList.length; i++) {
                    const test = testList[i];
                    showTestLoading(test.test_id);

                    try {
                        const result = await runTest(test.test_id);
                        updateTestStatus(test.test_id, result);
                    } catch (error) {
                        updateTestStatus(test.test_id, {
                            test_id: test.test_id,
                            name: test.name,
                            passed: false,
                            message: 'خطا در اجرای تست: ' + error.message,
                            details: {}
                        });
                    }

                    // Small delay between tests for better UX
                    await new Promise(resolve => setTimeout(resolve, 300));
                }

                // Show summary
                showSummary();
                $rerunButton.show();
            } catch (error) {
                $error.html(`<strong>خطا:</strong> ${escapeHtml(error.message)}`).show();
            } finally {
                $loading.hide();
            }
        }

        /**
         * Show summary of results
         */
        function showSummary() {
            const total = testResults.length;
            const passed = testResults.filter(r => r.passed).length;
            const failed = total - passed;
            const percentage = total > 0 ? Math.round((passed / total) * 100) : 0;

            let summaryHtml = `
                <div style="display: flex; align-items: center; gap: 20px; margin-bottom: 15px;">
                    <div>
                        <strong>کل تست‌ها:</strong> ${total}
                    </div>
                    <div style="color: #46b450;">
                        <strong>موفق:</strong> ${passed}
                    </div>
                    <div style="color: #dc3232;">
                        <strong>ناموفق:</strong> ${failed}
                    </div>
                    <div>
                        <strong>درصد موفقیت:</strong> ${percentage}%
                    </div>
                </div>
            `;

            if (failed > 0) {
                summaryHtml += '<div style="margin-top: 10px;"><strong>تست‌های ناموفق:</strong><ul style="margin: 10px 0; padding-right: 20px;">';
                testResults.filter(r => !r.passed).forEach(result => {
                    summaryHtml += `<li>${escapeHtml(result.name)}: ${escapeHtml(result.message)}</li>`;
                });
                summaryHtml += '</ul></div>';
            }

            $summaryContent.html(summaryHtml);
            $summary.show();
        }

        /**
         * Reset modal state
         */
        function resetModal() {
            $testsList.empty();
            $summary.hide();
            $loading.hide();
            $error.hide();
            $rerunButton.hide();
            tests = [];
            testResults = [];
        }

        /**
         * Open modal and run tests
         */
        function openModal() {
            resetModal();
            MicroModal.show('anar-system-diagnostics-modal');
            runAllTests();
        }

        // Event handlers
        $button.on('click', function(e) {
            e.preventDefault();
            openModal();
        });

        $rerunButton.on('click', function(e) {
            e.preventDefault();
            runAllTests();
        });

        // Close modal handler
        $modal.on('click', '[data-micromodal-close]', function() {
            resetModal();
        });
    });
}

