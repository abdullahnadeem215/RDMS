    <!-- Footer Section -->
    <div class="footer" style="text-align: center; padding: 30px; color: var(--text-secondary); font-size: 13px; margin-top: 55px; border-top: 1px solid var(--border);">
        <p>Rural Development Management System (RDMS) | Umeed-e-Sahar Foundation © <?php echo date('Y'); ?></p>
    </div>
    
    <!-- Shared Frontend Logic (Alerts, Modals, Tabs) -->
    <script>
        // Auto-fade notifications/alerts
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.6s ease';
                    setTimeout(function() { alert.remove(); }, 600);
                }, 5000);
            });
        });

        // Modal Handlers
        function openModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.add('active');
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.classList.remove('active');
            }
        }

        // Close modal when user clicks outside modal card content area
        document.querySelectorAll('.modal-overlay').forEach(function(overlay) {
            overlay.addEventListener('click', function(e) {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        });

        // Tab Switching Handler
        function switchTab(tabId, btnElement) {
            // Find parent context to prevent mixing tabs on pages with multiple tabs
            const parentContext = btnElement.closest('.card') || document;
            
            // Deactivate all sibling contents & buttons
            parentContext.querySelectorAll('.tab-content').forEach(function(el) {
                el.classList.remove('active');
            });
            parentContext.querySelectorAll('.tab-btn').forEach(function(el) {
                el.classList.remove('active');
            });
            
            // Activate selected tab content & active class on button
            const selectedContent = document.getElementById(tabId);
            if (selectedContent) {
                selectedContent.classList.add('active');
            }
            btnElement.classList.add('active');
        }
    </script>
</body>
</html>
