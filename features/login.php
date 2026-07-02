<?php

/**
 * Quick Login → Temporary Login links.
 *
 * Manage passwordless, time-limited login links (powered by WP_Arzo_Temp_Login).
 * Handles the tl_create / tl_delete / tl_toggle AJAX operations (JSON), then renders
 * the management table + create form. The actual sign-in happens site-wide in the
 * engine's `init` hook, not here.
 *
 * @package WP_Arzo
 * @version 7.0
 */

if (!defined('ABSPATH')) {
    exit;
}

/* ----------------------------------------------------------- AJAX ops */
if (isset($_GET['operation']) && in_array($_GET['operation'], array('tl_create', 'tl_delete', 'tl_toggle', 'tl_invite'), true)) {
    header('Content-Type: application/json');
    if (!current_user_can('manage_options') || !check_ajax_referer('wp_arzo_ajax', 'nonce', false)) {
        echo wp_json_encode(array('success' => false, 'message' => 'Security check failed'));
        exit;
    }
    if (!class_exists('WP_Arzo_Temp_Login')) {
        echo wp_json_encode(array('success' => false, 'message' => 'Temporary login engine unavailable.'));
        exit;
    }
    $engine = WP_Arzo_Temp_Login::instance();
    $op = $_GET['operation'];

    if ($op === 'tl_create') {
        $res = $engine->create(array(
            'name'       => isset($_POST['name']) ? wp_unslash($_POST['name']) : '',
            'email'      => isset($_POST['email']) ? wp_unslash($_POST['email']) : '',
            'role'       => isset($_POST['role']) ? sanitize_key(wp_unslash($_POST['role'])) : 'administrator',
            'redirect'   => isset($_POST['redirect']) ? wp_unslash($_POST['redirect']) : '',
            'expiry'     => isset($_POST['expiry']) ? sanitize_key(wp_unslash($_POST['expiry'])) : '1day',
            'expire_at'  => isset($_POST['expire_at']) ? wp_unslash($_POST['expire_at']) : '',
            'max_logins' => isset($_POST['max_logins']) ? (int) $_POST['max_logins'] : 0,
        ));
        if (is_wp_error($res)) {
            echo wp_json_encode(array('success' => false, 'message' => $res->get_error_message()));
        } else {
            echo wp_json_encode(array('success' => true, 'login_url' => $res['login_url']));
        }
        exit;
    }
    if ($op === 'tl_delete') {
        $ok = $engine->delete(isset($_POST['id']) ? (int) $_POST['id'] : 0);
        echo wp_json_encode(array('success' => (bool) $ok));
        exit;
    }
    if ($op === 'tl_toggle') {
        $ok = $engine->set_status(
            isset($_POST['id']) ? (int) $_POST['id'] : 0,
            isset($_POST['status']) ? sanitize_key(wp_unslash($_POST['status'])) : 'active'
        );
        echo wp_json_encode(array('success' => (bool) $ok));
        exit;
    }
    if ($op === 'tl_invite') {
        $res = $engine->send_invite(
            isset($_POST['id']) ? (int) $_POST['id'] : 0,
            isset($_POST['message']) ? wp_unslash($_POST['message']) : ''
        );
        if (is_wp_error($res)) {
            echo wp_json_encode(array('success' => false, 'message' => $res->get_error_message()));
        } else {
            echo wp_json_encode(array('success' => true, 'message' => 'Invitation sent.'));
        }
        exit;
    }
    exit;
}

function handleQuickLogin()
{
    $engine  = class_exists('WP_Arzo_Temp_Login') ? WP_Arzo_Temp_Login::instance() : null;
    $logins  = $engine ? $engine->all() : array();
    $roles   = function_exists('get_editable_roles') ? get_editable_roles() : array();
    $now     = time();
    ?>
    <div class="content">
        <h1>Temporary Login Links</h1>
        <p style="color:var(--muted-text); margin-top:-6px;">
            Create passwordless, time-limited links that sign someone in as a chosen role — perfect for support, clients, or developers. No password sharing, auto-expiring, and revocable any time.
        </p>

        <!-- Create -->
        <div style="background:var(--arzo-bg-elev); border:1px solid var(--arzo-border); padding:18px; border-radius:var(--radius-global); margin:18px 0;">
            <h4 style="margin-top:0;">Create a login link</h4>
            <div style="display:grid; grid-template-columns:repeat(auto-fit,minmax(180px,1fr)); gap:12px;">
                <div class="form-group" style="margin:0;">
                    <label>Name <span style="color:var(--muted-text);">(optional)</span></label>
                    <input type="text" id="tl-name" class="form-control" placeholder="e.g. Acme support">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Email <span style="color:var(--muted-text);">(optional)</span></label>
                    <input type="email" id="tl-email" class="form-control" placeholder="auto-generated if blank">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Role</label>
                    <select id="tl-role" class="form-control">
                        <?php foreach ($roles as $slug => $r) {
                            $sel = ($slug === 'administrator') ? ' selected' : '';
                            echo '<option value="' . esc_attr($slug) . '"' . $sel . '>' . esc_html($r['name']) . '</option>';
                        } ?>
                    </select>
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Expires</label>
                    <select id="tl-expiry" class="form-control">
                        <option value="1hour">In 1 hour</option>
                        <option value="6hours">In 6 hours</option>
                        <option value="1day" selected>In 1 day</option>
                        <option value="1week">In 1 week</option>
                        <option value="1month">In 1 month</option>
                        <option value="custom">Custom date…</option>
                    </select>
                </div>
                <div class="form-group" style="margin:0; display:none;" id="tl-custom-wrap">
                    <label>Custom expiry (UTC)</label>
                    <input type="datetime-local" id="tl-expire-at" class="form-control">
                </div>
                <div class="form-group" style="margin:0;">
                    <label>Max logins <span style="color:var(--muted-text);">(0 = ∞)</span></label>
                    <input type="number" id="tl-max" class="form-control" value="0" min="0">
                </div>
                <div class="form-group" style="margin:0; grid-column:1/-1;">
                    <label>Redirect after login <span style="color:var(--muted-text);">(optional)</span></label>
                    <input type="text" id="tl-redirect" class="form-control" placeholder="<?php echo esc_attr(admin_url()); ?>">
                </div>
            </div>
            <button type="button" class="btn" id="tl-create" style="margin-top:14px;">Generate login link</button>
            <div id="tl-result" style="display:none; margin-top:14px; padding:12px; background:var(--background-medium); border:1px solid var(--accent-color); border-radius:var(--radius-global);">
                <strong style="color:var(--accent-color);">Login link ready</strong>
                <div style="display:flex; gap:8px; margin-top:8px;">
                    <input type="text" id="tl-result-url" class="form-control" readonly onclick="this.select()">
                    <button type="button" class="btn btn-sm" onclick="tlCopy(document.getElementById('tl-result-url').value, this)">Copy</button>
                </div>
            </div>
        </div>

        <!-- List -->
        <h4>Active &amp; recent links</h4>
        <table>
            <tr>
                <th>User</th>
                <th>Role</th>
                <th>Expires (UTC)</th>
                <th>Logins</th>
                <th>Last login</th>
                <th>Last IP</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            <?php if (empty($logins)) : ?>
                <tr><td colspan="8" style="color:var(--muted-text);">No temporary logins yet.</td></tr>
            <?php else : foreach ($logins as $t) :
                $expired = $t['expire'] && $now > $t['expire'];
                $status  = $expired ? 'expired' : $t['status'];
                ?>
                <tr data-id="<?php echo (int) $t['id']; ?>">
                    <td>
                        <strong><?php echo esc_html($t['name']); ?></strong>
                        <div style="font-size:12px; color:var(--muted-text);"><?php echo esc_html($t['email']); ?></div>
                    </td>
                    <td><?php echo esc_html($t['role']); ?></td>
                    <td><?php echo $t['expire'] ? esc_html(gmdate('Y-m-d H:i', $t['expire'])) : '—'; ?></td>
                    <td><?php echo (int) $t['count']; ?><?php echo $t['max'] > 0 ? ' / ' . (int) $t['max'] : ''; ?></td>
                    <td><?php echo $t['last_login'] ? esc_html($t['last_login']) : '<span style="color:var(--muted-text);">never</span>'; ?></td>
                    <td><?php echo !empty($t['last_ip']) ? esc_html($t['last_ip']) : '<span style="color:var(--muted-text);">—</span>'; ?></td>
                    <td>
                        <?php
                        $badge = $status === 'active' ? 'badge-active' : 'badge-inactive';
                        echo '<span class="badge ' . $badge . '">' . esc_html(strtoupper($status)) . '</span>';
                        ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <button type="button" class="btn btn-sm" title="Copy link" onclick="tlCopy('<?php echo esc_js($t['login_url']); ?>', this)">Copy</button>
                        <button type="button" class="btn btn-sm tl-invite" title="Email a branded invitation with this link">Email invite</button>
                        <button type="button" class="btn btn-sm tl-toggle" data-status="<?php echo $t['status'] === 'active' ? 'disabled' : 'active'; ?>"><?php echo $t['status'] === 'active' ? 'Disable' : 'Enable'; ?></button>
                        <button type="button" class="btn btn-sm btn-danger tl-delete">Delete</button>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>

    <script>
    (function () {
        var cfg = window.wpArzoConfig || {};
        function post(op, data) {
            var body = new FormData();
            body.append('nonce', cfg.nonce || '');
            Object.keys(data || {}).forEach(function (k) { body.append(k, data[k]); });
            return fetch(cfg.ajaxUrl + '?action=wp_arzo_standalone&tab=login&operation=' + op, { method: 'POST', body: body, credentials: 'same-origin' }).then(function (r) { return r.json(); });
        }
        window.tlCopy = function (text, btn) {
            navigator.clipboard.writeText(text).then(function () {
                var t = btn.textContent; btn.textContent = 'Copied!';
                setTimeout(function () { btn.textContent = t; }, 1200);
            });
        };
        var expiry = document.getElementById('tl-expiry');
        if (expiry) expiry.addEventListener('change', function () {
            document.getElementById('tl-custom-wrap').style.display = this.value === 'custom' ? '' : 'none';
        });
        var createBtn = document.getElementById('tl-create');
        if (createBtn) createBtn.addEventListener('click', function () {
            createBtn.disabled = true;
            post('tl_create', {
                name: (document.getElementById('tl-name') || {}).value || '',
                email: (document.getElementById('tl-email') || {}).value || '',
                role: (document.getElementById('tl-role') || {}).value || 'administrator',
                expiry: (document.getElementById('tl-expiry') || {}).value || '1day',
                expire_at: (document.getElementById('tl-expire-at') || {}).value || '',
                redirect: (document.getElementById('tl-redirect') || {}).value || '',
                max_logins: (document.getElementById('tl-max') || {}).value || 0
            }).then(function (res) {
                createBtn.disabled = false;
                if (res && res.success) {
                    document.getElementById('tl-result-url').value = res.login_url;
                    document.getElementById('tl-result').style.display = '';
                    setTimeout(function () { location.reload(); }, 1500);
                } else {
                    alert((res && res.message) || 'Could not create login.');
                }
            }).catch(function () { createBtn.disabled = false; alert('Request failed.'); });
        });
        document.querySelectorAll('.tl-delete').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (!confirm('Delete this temporary login? The user account is removed and its content reassigned to you.')) return;
                var id = btn.closest('tr').getAttribute('data-id');
                post('tl_delete', { id: id }).then(function (res) {
                    if (res && res.success) { btn.closest('tr').remove(); } else { alert('Delete failed.'); }
                });
            });
        });
        document.querySelectorAll('.tl-invite').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.closest('tr').getAttribute('data-id');
                var message = prompt('Optional personal note to include in the invitation email (leave blank for none):', '');
                if (message === null) return; // cancelled
                btn.disabled = true;
                var label = btn.textContent; btn.textContent = 'Sending…';
                post('tl_invite', { id: id, message: message }).then(function (res) {
                    btn.disabled = false;
                    if (res && res.success) {
                        btn.textContent = 'Sent!';
                        setTimeout(function () { btn.textContent = label; }, 1500);
                    } else {
                        btn.textContent = label;
                        alert((res && res.message) || 'Could not send the invitation.');
                    }
                }).catch(function () { btn.disabled = false; btn.textContent = label; alert('Request failed.'); });
            });
        });
        document.querySelectorAll('.tl-toggle').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var id = btn.closest('tr').getAttribute('data-id');
                post('tl_toggle', { id: id, status: btn.getAttribute('data-status') }).then(function (res) {
                    if (res && res.success) { location.reload(); } else { alert('Update failed.'); }
                });
            });
        });
    })();
    </script>
<?php
}

handleQuickLogin();
