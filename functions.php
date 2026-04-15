<?php
// Función para encolar estilos y scripts
function tenis_dedeportes_scripts()
{
    $theme_version = filemtime(get_stylesheet_directory() . '/style.css');
    wp_enqueue_style('tenis-dedeportes-style', get_stylesheet_uri(), array(), $theme_version);
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

/**
 * Obtiene la URL del perfil de un jugador si existe la página.
 */
function get_player_profile_url($player_name)
{
    if (empty($player_name))
        return null;
    $slug = sanitize_title($player_name);
    // get_page_by_path busca por el slug de la página
    $page = get_page_by_path($slug);
    if ($page) {
        return get_permalink($page->ID);
    }
    return null;
}
?>