<footer class="site-footer">
    <div class="container">
        <p>&copy;
            <?php echo date('Y'); ?>
            <?php bloginfo('name'); ?>. Todos los derechos reservados.
        </p>
    </div>
</footer>

<?php wp_footer(); ?>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        const toggle = document.querySelector('.menu-toggle');
        const nav = document.querySelector('.main-navigation');
        if (toggle && nav) {
            toggle.addEventListener('click', function () {
                nav.classList.toggle('toggled');
                const expanded = nav.classList.contains('toggled');
                toggle.setAttribute('aria-expanded', expanded);
            });
        }
    });
</script>
</body>

</html>