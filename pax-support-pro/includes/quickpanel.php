<?php
/**
 * Front-end quick panel tools.
 *
 * @package PAX_Support_Pro
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

function pax_sup_render_quick_panel() {
    if ( is_admin() || ! current_user_can( 'manage_options' ) ) {
        return;
    }

    $opts      = pax_sup_get_options();
    if ( empty( $opts['enabled'] ) || empty( $opts['enable_chat'] ) ) {
        return;
    }
    $nonce     = wp_create_nonce( 'pax_quicksave' );
    $ajax_url  = admin_url( 'admin-ajax.php' );
    $position  = $opts['launcher_position'];
    $accent    = $opts['color_accent'];
    $speed_on  = ! empty( $opts['enable_speed'] );
    ?>
    <style id="pax-quickpanel-css">
        #pax-quick-gear{width:34px;height:34px;border-radius:10px;border:1px solid var(--pax-border);background:#20252b;display:flex;align-items:center;justify-content:center;cursor:pointer;margin-inline-end:6px}
        #pax-quick-gear svg{width:16px;height:16px;fill:#cfd6de}
        #pax-quick-panel{position:fixed;inset:0;z-index:2147483647;display:none}
        #pax-quick-panel .mask{position:absolute;inset:0;background:rgba(0,0,0,.35)}
        #pax-quick-panel .card{position:absolute;right:18px;bottom:100px;width:340px;background:#1b1f25;color:#e8eaf0;border:1px solid var(--pax-border);border-radius:12px;box-shadow:0 18px 48px rgba(0,0,0,.55);padding:12px}
        #pax-quick-panel .row{display:flex;align-items:center;justify-content:space-between;gap:10px;margin:8px 0}
        #pax-quick-panel input[type="number"],#pax-quick-panel select,#pax-quick-panel input[type="text"]{width:120px;background:#0d0f11;border:1px solid var(--pax-border);border-radius:8px;color:#fff;padding:6px}
        #pax-quick-panel .actions{display:flex;justify-content:flex-end;gap:8px;margin-top:10px}
        #pax-quick-panel button{border:none;border-radius:8px;padding:8px 10px;cursor:pointer}
        #pax-quick-save{background:var(--pax-accent);color:#fff}
        #pax-quick-close{background:#2f3338;color:#cdd}
    </style>
    <div id="pax-quick-panel" aria-hidden="true">
        <div class="mask"></div>
        <div class="card">
            <div class="row"><strong><?php echo esc_html__( 'Quick Appearance', 'pax-support-pro' ); ?></strong></div>
            <div class="row"><?php esc_html_e( 'Position', 'pax-support-pro' ); ?>
                <select id="qp-pos">
                    <option value="bottom-left"><?php esc_html_e( 'Bottom Left', 'pax-support-pro' ); ?></option>
                    <option value="bottom-right"><?php esc_html_e( 'Bottom Right', 'pax-support-pro' ); ?></option>
                    <option value="top-left"><?php esc_html_e( 'Top Left', 'pax-support-pro' ); ?></option>
                    <option value="top-right"><?php esc_html_e( 'Top Right', 'pax-support-pro' ); ?></option>
                </select>
            </div>
            <div class="row"><?php esc_html_e( 'Accent', 'pax-support-pro' ); ?> <input id="qp-accent" type="text" placeholder="#e53935"></div>
            <div class="row"><?php esc_html_e( 'Speed', 'pax-support-pro' ); ?>
                <select id="qp-speed">
                    <option value="off"><?php esc_html_e( 'Off', 'pax-support-pro' ); ?></option>
                    <option value="on"><?php esc_html_e( 'On', 'pax-support-pro' ); ?></option>
                </select>
            </div>
            <div class="actions">
                <button id="pax-quick-close" type="button"><?php esc_html_e( 'Close', 'pax-support-pro' ); ?></button>
                <button id="pax-quick-save" type="button"><?php esc_html_e( 'Save', 'pax-support-pro' ); ?></button>
            </div>
        </div>
    </div>
    <script id="pax-quickpanel-js">
    (function(){
        const head = document.querySelector('#pax-chat .pax-header');
        if(head && !document.getElementById('pax-quick-gear')){
            const gear = document.createElement('button');
            gear.id='pax-quick-gear';
            gear.type='button';
            gear.innerHTML='<svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 8a4 4 0 1 0 4 4 4 4 0 0 0-4-4Zm9.4 4a7.4 7.4 0 0 0-.2-1.8l2.1-1.6-2-3.5-2.5 1a8.2 8.2 0 0 0-1.6-.9l-.4-2.7H9.2l-.4 2.7a8.2 8.2 0 0 0-1.6.9l-2.5-1-2 3.5 2.1 1.6a7.4 7.4 0 0 0 0 3.6l-2.1 1.6 2 3.5 2.5-1a8.2 8.2 0 0 0 1.6.9l.4 2.7h5.6l.4-2.7a8.2 8.2 0 0 0 1.6-.9l2.5 1 2-3.5-2.1-1.6a7.4 7.4 0 0 0 .2-1.8Z"/></svg>';
            const closeBtn = document.getElementById('pax-close');
            if (closeBtn) {
                closeBtn.before(gear);
            } else {
                head.prepend(gear);
            }
            gear.addEventListener('click', function(){
                document.getElementById('pax-quick-panel').style.display='block';
            });
        }

        const panel = document.getElementById('pax-quick-panel');
        const posSel = document.getElementById('qp-pos');
        const accInp = document.getElementById('qp-accent');
        const spSel  = document.getElementById('qp-speed');
        posSel.value = <?php echo wp_json_encode( $position ); ?>;
        accInp.value = <?php echo wp_json_encode( $accent ); ?>;
        spSel.value  = <?php echo wp_json_encode( $speed_on ? 'on' : 'off' ); ?>;

        document.getElementById('pax-quick-close').onclick = function(){
            panel.style.display='none';
        };
        document.querySelector('#pax-quick-panel .mask').onclick = function(){
            panel.style.display='none';
        };

        document.getElementById('pax-quick-save').onclick = async function(){
            const pos = posSel.value;
            const accent = accInp.value.trim();
            const speed = spSel.value;
            this.disabled = true;
            this.textContent = 'Saving…';
            try{
                const form = new FormData();
                form.append('action','pax_quicksave');
                form.append('_ajax_nonce', <?php echo wp_json_encode( $nonce ); ?>);
                form.append('launcher_position', pos);
                form.append('color_accent', accent);
                form.append('speed', speed);
                const response = await fetch(<?php echo wp_json_encode( $ajax_url ); ?>, {method:'POST', body: form});
                const data = await response.json();
                this.disabled = false;
                this.textContent = <?php echo wp_json_encode( __( 'Save', 'pax-support-pro' ) ); ?>;
                if(data && data.ok){
                    document.documentElement.style.setProperty('--pax-accent', accent);
                    document.documentElement.classList.toggle('pax-speed', speed === 'on');
                    const launcher = document.getElementById('pax-launcher');
                    const chat = document.getElementById('pax-chat');
                    if (launcher && chat) {
                        launcher.style.left = launcher.style.right = launcher.style.top = launcher.style.bottom = '';
                        chat.style.left = chat.style.right = chat.style.top = chat.style.bottom = '';
                        if (pos === 'bottom-left') {
                            launcher.style.left = '16px';
                            launcher.style.bottom = '16px';
                            chat.style.left = '14px';
                            chat.style.bottom = '90px';
                        }
                        if (pos === 'bottom-right') {
                            launcher.style.right = '16px';
                            launcher.style.bottom = '16px';
                            chat.style.right = '14px';
                            chat.style.bottom = '90px';
                        }
                        if (pos === 'top-left') {
                            launcher.style.left = '16px';
                            launcher.style.top = '16px';
                            chat.style.left = '14px';
                            chat.style.top = '90px';
                        }
                        if (pos === 'top-right') {
                            launcher.style.right = '16px';
                            launcher.style.top = '16px';
                            chat.style.right = '14px';
                            chat.style.top = '90px';
                        }
                    }
                    panel.style.display='none';
                    window.alert('Saved.');
                } else {
                    window.alert('Failed to save.');
                }
            } catch (error) {
                this.disabled = false;
                this.textContent = <?php echo wp_json_encode( __( 'Save', 'pax-support-pro' ) ); ?>;
                window.alert('Network error.');
            }
        };
    })();
    </script>
    <?php
}

add_action( 'wp_footer', 'pax_sup_render_quick_panel' );

function pax_sup_handle_quick_save() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_send_json_error( array( 'error' => 'cap' ) );
    }

    check_ajax_referer( 'pax_quicksave' );

    $options = pax_sup_get_options();

    if ( isset( $_POST['launcher_position'] ) ) {
        $position = sanitize_text_field( wp_unslash( $_POST['launcher_position'] ) );
        $valid    = array( 'bottom-left', 'bottom-right', 'top-left', 'top-right' );
        if ( in_array( $position, $valid, true ) ) {
            $options['launcher_position'] = $position;
        }
    }

    if ( isset( $_POST['color_accent'] ) ) {
        $hex = sanitize_hex_color( wp_unslash( $_POST['color_accent'] ) );
        if ( $hex ) {
            $options['color_accent'] = $hex;
        }
    }

    if ( isset( $_POST['speed'] ) ) {
        $speed                  = sanitize_text_field( wp_unslash( $_POST['speed'] ) );
        $options['enable_speed'] = ( 'on' === $speed ) ? 1 : 0;
    }

    pax_sup_update_options( $options );

    wp_send_json( array( 'ok' => true ) );
}

add_action( 'wp_ajax_pax_quicksave', 'pax_sup_handle_quick_save' );