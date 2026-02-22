<?php
/**
 * Theme Management Feature
 *
 * @package WP_Arzo
 * @version 6.0
 */

// Security check
if (!defined('ABSPATH')) {
    exit;
}

function showThemes()
{
    $themes = wp_get_themes();
    $current_theme = wp_get_theme();

    ?>
        <div class="content">
            <h2>Theme Management</h2>
            <table>
                <tr>
                    <th>Theme Name</th>
                    <th>Version</th>
                    <th>Status</th>
                </tr>
                <?php
                foreach ($themes as $theme) {
                    $is_active = ($theme->get_stylesheet() === $current_theme->get_stylesheet());
                    echo '<tr>';
                    echo '<td>' . $theme->get('Name') . '</td>';
                    echo '<td>' . $theme->get('Version') . '</td>';
                    echo '<td>' . ($is_active ? 'Active' : 'Inactive') . '</td>';
                    echo '</tr>';
                }
                ?>
            </table>
        </div>
        <?php
}

// Call the function
showThemes();
