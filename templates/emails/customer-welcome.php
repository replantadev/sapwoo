<?php
/**
 * Plantilla de email de bienvenida para clientes B2B
 * 
 * Esta plantilla puede ser sobrescrita copiándola a:
 * tu-tema/sapwoo/emails/customer-welcome.php
 * 
 * Variables disponibles:
 * - $customer_name: Nombre del cliente
 * - $username: Nombre de usuario (CardCode)
 * - $reset_url: URL para establecer contraseña
 * - $site_name: Nombre del sitio
 * - $site_url: URL del sitio
 * - $logo_html: HTML del logo
 * - $colors: Array con colores (primary, secondary, text, background)
 * 
 * @package SAPWC
 * @since 1.3.0
 */

if (!defined('ABSPATH')) {
    exit;
}

$primary = esc_attr($colors['primary']);
$text_color = esc_attr($colors['text']);
$bg_color = esc_attr($colors['background']);
$year = date('Y');
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php printf(esc_html__('Bienvenido a %s', 'sapwoo'), esc_html($site_name)); ?></title>
</head>
<body style="margin: 0; padding: 0; background-color: <?php echo $bg_color; ?>; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, sans-serif;">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background-color: <?php echo $bg_color; ?>;">
        <tr>
            <td align="center" style="padding: 40px 20px;">
                <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width: 600px; background-color: #ffffff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08);">
                    
                    <!-- Header con logo -->
                    <tr>
                        <td align="center" style="padding: 40px 40px 30px; background-color: <?php echo $primary; ?>; border-radius: 8px 8px 0 0;">
                            <div style="background: white; padding: 20px; border-radius: 8px; display: inline-block;">
                                <?php echo $logo_html; ?>
                            </div>
                        </td>
                    </tr>
                    
                    <!-- Contenido principal -->
                    <tr>
                        <td style="padding: 40px;">
                            <h2 style="margin: 0 0 20px; color: <?php echo $text_color; ?>; font-size: 24px; font-weight: 600;">
                                <?php printf(esc_html__('¡Hola %s!', 'sapwoo'), esc_html($customer_name)); ?>
                            </h2>
                            
                            <p style="margin: 0 0 20px; color: <?php echo $text_color; ?>; font-size: 16px; line-height: 1.6;">
                                <?php printf(
                                    esc_html__('Se ha creado una cuenta para ti en %s. Ya puedes acceder a nuestra plataforma para realizar pedidos y gestionar tu cuenta.', 'sapwoo'),
                                    '<strong>' . esc_html($site_name) . '</strong>'
                                ); ?>
                            </p>
                            
                            <p style="margin: 0 0 30px; color: <?php echo $text_color; ?>; font-size: 16px; line-height: 1.6;">
                                <?php esc_html_e('Para empezar, necesitas establecer tu contraseña. Haz clic en el botón de abajo:', 'sapwoo'); ?>
                            </p>
                            
                            <!-- Botón CTA -->
                            <table role="presentation" width="100%" cellspacing="0" cellpadding="0">
                                <tr>
                                    <td align="center" style="padding: 10px 0 30px;">
                                        <a href="<?php echo esc_url($reset_url); ?>" 
                                           style="display: inline-block; padding: 16px 40px; background-color: <?php echo $primary; ?>; color: #ffffff; text-decoration: none; font-size: 16px; font-weight: 600; border-radius: 6px; box-shadow: 0 2px 4px rgba(0,0,0,0.1);">
                                            <?php esc_html_e('Establecer mi contraseña', 'sapwoo'); ?>
                                        </a>
                                    </td>
                                </tr>
                            </table>
                            
                            <!-- Info adicional -->
                            <div style="background-color: <?php echo $bg_color; ?>; padding: 20px; border-radius: 6px; margin-bottom: 20px;">
                                <p style="margin: 0 0 10px; color: <?php echo $text_color; ?>; font-size: 14px;">
                                    <strong><?php esc_html_e('Tu nombre de usuario:', 'sapwoo'); ?></strong> <?php echo esc_html($username); ?>
                                </p>
                                <p style="margin: 0; color: #666; font-size: 13px;">
                                    <?php esc_html_e('Este enlace es válido durante 24 horas. Si expira, puedes solicitar uno nuevo desde "¿Olvidaste tu contraseña?".', 'sapwoo'); ?>
                                </p>
                            </div>
                            
                            <p style="margin: 0; color: <?php echo $text_color; ?>; font-size: 16px; line-height: 1.6;">
                                <?php esc_html_e('Si tienes alguna duda, no dudes en contactarnos.', 'sapwoo'); ?>
                            </p>
                            
                            <p style="margin: 20px 0 0; color: <?php echo $text_color; ?>; font-size: 16px;">
                                <?php esc_html_e('¡Te esperamos!', 'sapwoo'); ?><br>
                                <strong><?php printf(esc_html__('El equipo de %s', 'sapwoo'), esc_html($site_name)); ?></strong>
                            </p>
                        </td>
                    </tr>
                    
                    <!-- Footer -->
                    <tr>
                        <td style="padding: 30px 40px; background-color: <?php echo $bg_color; ?>; border-radius: 0 0 8px 8px; border-top: 1px solid #e5e5e5;">
                            <p style="margin: 0 0 10px; color: #888; font-size: 13px; text-align: center;">
                                © <?php echo $year; ?> <?php echo esc_html($site_name); ?>. <?php esc_html_e('Todos los derechos reservados.', 'sapwoo'); ?>
                            </p>
                            <p style="margin: 0; color: #888; font-size: 12px; text-align: center;">
                                <a href="<?php echo esc_url($site_url); ?>" style="color: <?php echo $primary; ?>; text-decoration: none;"><?php esc_html_e('Visitar tienda', 'sapwoo'); ?></a>
                            </p>
                        </td>
                    </tr>
                    
                </table>
                
                <!-- Enlace alternativo -->
                <p style="margin: 30px 0 0; color: #888; font-size: 12px; text-align: center; max-width: 600px;">
                    <?php esc_html_e('Si el botón no funciona, copia y pega este enlace en tu navegador:', 'sapwoo'); ?><br>
                    <a href="<?php echo esc_url($reset_url); ?>" style="color: <?php echo $primary; ?>; word-break: break-all;"><?php echo esc_url($reset_url); ?></a>
                </p>
            </td>
        </tr>
    </table>
</body>
</html>
