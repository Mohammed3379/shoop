            </div><!-- end admin-content -->
        </main>
    </div><!-- end admin-wrapper -->
    
    <!-- Toast Container -->
    <div class="toast-container"></div>
    
    <!-- Admin Scripts -->
    <?php 
    $admin_base_js = '';
    if (basename(dirname($_SERVER['PHP_SELF'])) === 'includes') {
        $admin_base_js = '../';
    }
    ?>
    <script src="<?php echo $admin_base_js; ?>js/admin-script.js"></script>
    
    <?php if (isset($extra_js)): ?>
        <?php echo $extra_js; ?>
    <?php endif; ?>
</body>
</html>
