<?php
// Función para encolar estilos y scripts
function tenis_dedeportes_scripts()
{
    wp_enqueue_style('tenis-dedeportes-style', get_stylesheet_uri(), array(), '1.0.0');
    // wp_enqueue_script( 'tenis-dedeportes-script', get_template_directory_uri() . '/js/main.js', array('jquery'), '1.0.0', true );
}
add_action('wp_enqueue_scripts', 'tenis_dedeportes_scripts');

// Soporte del tema
function tenis_dedeportes_setup()
{
    add_theme_support('title-tag');
    add_theme_support('post-thumbnails');
    register_nav_menus(array(
        'menu-principal' => __('Menú Principal', 'tenis-dedeportes'),
    ));
}
add_action('after_setup_theme', 'tenis_dedeportes_setup');

// Conexión a la base de datos de estadísticas (PDO)
// REEMPLAZAR ESTOS DATOS en producción con los del hosting
function get_tenis_db_connection()
{
    $host = 'localhost';
    $dbname = 'pjdmenag_tenis'; // E.g. pjdmenag_tenis
    $user = 'pjdmenag_tenis';
    $password = '.^NRsa!_OF^;';

    try {
        $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $user, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        return $pdo;
    } catch (PDOException $e) {
        error_log("Error de conexión a la BD de tenis: " . $e->getMessage());
        return "Error PDO: " . $e->getMessage();
    }
}
?>