<?php
/**
 * Mailer de Bienvenida para nuevos clientes B2B
 * 
 * Envía emails personalizados con la paleta de colores del sitio,
 * logo y link para establecer contraseña.
 * 
 * @package SAPWC
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

class SAPWC_Welcome_Mailer
{
    /**
     * Envía el email de bienvenida
     * 
     * @param int $user_id ID del usuario
     * @return bool
     */
    public static function send($user_id)
    {
        $user = get_userdata($user_id);
        if (!$user || empty($user->user_email)) {
            return false;
        }

        // Generar key para reset de contraseña
        $key = get_password_reset_key($user);
        
        if (is_wp_error($key)) {
            error_log('[SAPWC Mailer] Error generando reset key para ' . $user->user_login . ': ' . $key->get_error_message());
            return false;
        }

        $reset_url = network_site_url("wp-login.php?action=rp&key={$key}&login=" . rawurlencode($user->user_login), 'login');

        // Datos del sitio
        $site_name = get_bloginfo('name');
        $site_url = home_url();
        
        // Obtener colores del tema
        $colors = self::get_site_colors();
        
        // Obtener logo
        $logo_html = self::get_logo_html();

        // Datos del usuario
        $customer_name = $user->first_name ?: $user->display_name ?: $user->user_login;
        
        // Renderizar plantilla
        $html_content = self::render_template([
            'customer_name' => $customer_name,
            'username' => $user->user_login,
            'reset_url' => $reset_url,
            'site_name' => $site_name,
            'site_url' => $site_url,
            'logo_html' => $logo_html,
            'colors' => $colors
        ]);

        // Asunto del email
        $subject = sprintf(__('¡Bienvenido/a a %s! Configura tu acceso', 'sapwoo'), $site_name);

        // Headers
        $headers = [
            'Content-Type: text/html; charset=UTF-8',
            'From: ' . $site_name . ' <' . get_option('admin_email') . '>'
        ];

        // Enviar
        $sent = wp_mail($user->user_email, $subject, $html_content, $headers);

        if ($sent) {
            update_user_meta($user_id, 'sapwc_welcome_email_sent', current_time('mysql'));
            SAPWC_Logger::log(null, 'welcome_email', 'success', "Email enviado a {$user->user_email} ({$user->user_login})");
        } else {
            SAPWC_Logger::log(null, 'welcome_email', 'error', "Fallo al enviar email a {$user->user_email}");
        }

        return $sent;
    }

    /**
     * Obtiene los colores del tema activo
     * 
     * @return array
     */
    private static function get_site_colors()
    {
        $defaults = [
            'primary' => '#0073aa',
            'secondary' => '#23282d',
            'accent' => '#00a0d2',
            'text' => '#333333',
            'background' => '#f7f7f7',
            'button_text' => '#ffffff'
        ];

        // Intentar obtener colores de WooCommerce
        if (function_exists('wc_get_theme_support')) {
            $wc_colors = wc_get_theme_support('woocommerce', 'colors', []);
            if (!empty($wc_colors['primary'])) {
                $defaults['primary'] = $wc_colors['primary'];
            }
        }

        // Intentar obtener del Customizer
        $custom_primary = get_theme_mod('primary_color', '');
        if (!empty($custom_primary)) {
            $defaults['primary'] = $custom_primary;
        }

        // Storefront specific
        $storefront_accent = get_theme_mod('storefront_accent_color', '');
        if (!empty($storefront_accent)) {
            $defaults['primary'] = $storefront_accent;
        }

        // Astra specific
        $astra_primary = get_option('astra-settings', []);
        if (!empty($astra_primary['link-color'])) {
            $defaults['primary'] = $astra_primary['link-color'];
        }

        // Permitir override desde opciones del plugin
        $custom_color = get_option('sapwc_email_primary_color', '');
        if (!empty($custom_color)) {
            $defaults['primary'] = $custom_color;
        }

        return $defaults;
    }

    /**
     * Obtiene el HTML del logo del sitio
     * 
     * @return string
     */
    private static function get_logo_html()
    {
        // Intentar logo personalizado de WooCommerce
        if (function_exists('wc_get_email_logo')) {
            $wc_logo = get_option('woocommerce_email_header_image');
            if (!empty($wc_logo)) {
                return '<img src="' . esc_url($wc_logo) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="max-width: 200px; height: auto;">';
            }
        }

        // Logo del tema
        $custom_logo_id = get_theme_mod('custom_logo');
        if ($custom_logo_id) {
            $logo_url = wp_get_attachment_image_url($custom_logo_id, 'medium');
            if ($logo_url) {
                return '<img src="' . esc_url($logo_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="max-width: 200px; height: auto;">';
            }
        }

        // Site icon como fallback
        $site_icon_id = get_option('site_icon');
        if ($site_icon_id) {
            $icon_url = wp_get_attachment_image_url($site_icon_id, 'full');
            if ($icon_url) {
                return '<img src="' . esc_url($icon_url) . '" alt="' . esc_attr(get_bloginfo('name')) . '" style="max-width: 80px; height: auto;">';
            }
        }

        // Solo texto como último recurso
        return '<h1 style="margin: 0; font-size: 28px; color: #333;">' . esc_html(get_bloginfo('name')) . '</h1>';
    }

    /**
     * Renderiza la plantilla del email
     * 
     * @param array $data Datos para la plantilla
     * @return string HTML del email
     */
    private static function render_template($data)
    {
        $primary = esc_attr($data['colors']['primary']);
        $text_color = esc_attr($data['colors']['text']);
        $bg_color = esc_attr($data['colors']['background']);

        // Verificar si existe plantilla personalizada en el tema
        $template_path = locate_template('sapwoo/emails/customer-welcome.php');
        
        if ($template_path) {
            ob_start();
            extract($data);
            include $template_path;
            return ob_get_clean();
        }

        // Plantilla inline por defecto
        return self::get_default_template($data);
    }

    /**
     * Plantilla HTML por defecto
     * 
     * @param array $data
     * @return string
     */
    private static function get_default_template($data)
    {
        $primary = esc_attr($data['colors']['primary']);
        $text_color = esc_attr($data['colors']['text']);
        $bg_color = esc_attr($data['colors']['background']);
        $customer_name = esc_html($data['customer_name']);
        $username = esc_html($data['username']);
        $reset_url = esc_url($data['reset_url']);
        $site_name = esc_html($data['site_name']);
        $site_url = esc_url($data['site_url']);
        $logo_html = $data['logo_html'];
        $year = date('Y');

        return <<<HTML
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenido a {$site_name}</title>
</head>
<body style="margin: 0; padding: 0; background-color: {$bg_color}; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: {$bg_color};">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    
                    <!-- Header con logo -->
                    <tr>
                        <td align="center" style="padding: 40px 40px 30px; background-color: {$primary}; border-radius: 8px 8px 0 0;">
                            <div style="background: white; padding: 20px; border-radius: 8px; display: inline-block;">
                                {$logo_html}
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Contenido principal -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="margin: 0 0 20px; color: {$text_color}; font-size: 24px; font-weight: 600;">
                                ¡Hola {$customer_name}!
                            </h2>
                            
                            <p style="margin: 0 0 20px; color: {$text_color}; font-size: 16px; line-height: 1.6;">
                                Se ha creado una cuenta para ti en <strong>{$site_name}</strong>. Ya puedes acceder a nuestra plataforma para realizar pedidos y gestionar tu cuenta.
                            </p>
                            
                            <p style="margin: 0 0 30px; color: {$text_color}; font-size: 16px; line-height: 1.6;">
                                Para empezar, necesitas <strong>establecer tu contraseña</strong>. Haz clic en el botón de abajo:
                            </p>
                            
                            <!-- Botón CTA -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" style="padding: 10px 0 30px;">
                                        <a href="{$reset_url}" 
                                           style="display: inline-block; padding: 16px 40px; background-color: {$primary}; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                            Establecer mi contraseña
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Info adicional -->
                            <div style="background-color: {$bg_color}; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                                <p style="margin: 0 0 10px; color: {$text_color}; font-size: 14px;">
                                    <strong>Tu nombre de usuario:</strong> {$username}
                                </p>
                                <p style="margin: 0; color: #666; font-size: 13px;">
                                    Este enlace es válido durante 24 horas. Si expira, puedes solicitar uno nuevo desde "¿Olvidaste tu contraseña?".
                                </p>
                            </div>
                            
                            <p style="margin: 0; color: {$text_color}; font-size: 16px; line-height: 1.6;">
                                Si tienes alguna duda, no dudes en contactarnos.
                            </p>
                            
                            <p style="margin: 20px 0 0; color: {$text_color}; font-size: 16px;">
                                ¡Te esperamos!<br>
                                <strong>El equipo de {$site_name}</strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: {$bg_color}; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e5e5;">
                            <p style="margin: 0 0 10px; color: #888; font-size: 13px; text-align: center;">
                                © {$year} {$site_name}. Todos los derechos reservados.
                            </p>
                            <p style="margin: 0; color: #888; font-size: 12px; text-align: center;">
                                <a href="{$site_url}" style="color: {$primary}; text-decoration: none;">Visitar tienda</a>
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
                <!-- Enlace alternativo -->
                <p style="margin: 30px 0 0; color: #888; font-size: 12px; text-align: center; max-width: 600px;">
                    Si el botón no funciona, copia y pega este enlace en tu navegador:<br>
                    <a href="{$reset_url}" style="color: {$primary}; word-break: break-all;">{$reset_url}</a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
HTML;
    }

    /**
     * Genera una vista previa del email (para admin)
     * 
     * @param int|null $user_id ID de usuario para datos reales (opcional)
     * @return string HTML del email
     */
    public static function get_preview($user_id = null)
    {
        if ($user_id) {
            $user = get_userdata($user_id);
            $customer_name = $user->first_name ?: $user->display_name;
            $username = $user->user_login;
        } else {
            $customer_name = 'Cliente Ejemplo';
            $username = 'CLIENTE001';
        }

        return self::render_template([
            'customer_name' => $customer_name,
            'username' => $username,
            'reset_url' => home_url('/wp-login.php?action=rp&key=PREVIEW_KEY&login=' . $username),
            'site_name' => get_bloginfo('name'),
            'site_url' => home_url(),
            'logo_html' => self::get_logo_html(),
            'colors' => self::get_site_colors()
        ]);
    }

    /**
     * Reenvía el email de bienvenida a un usuario existente
     * 
     * @param int $user_id
     * @return bool
     */
    public static function resend($user_id)
    {
        // Verificar que sea un cliente importado de SAP
        $cardcode = get_user_meta($user_id, 'sapwc_cardcode', true);
        if (empty($cardcode)) {
            return false;
        }

        return self::send($user_id);
    }
}
