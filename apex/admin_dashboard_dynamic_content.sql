DECLARE
    c_slot_capacity CONSTANT NUMBER := 20;

    l_total_products      NUMBER := 0;
    l_active_products     NUMBER := 0;
    l_low_stock_items     NUMBER := 0;
    l_out_of_stock_items  NUMBER := 0;
    l_total_traders       NUMBER := 0;
    l_active_traders      NUMBER := 0;
    l_orders_today        NUMBER := 0;
    l_orders_yesterday    NUMBER := 0;
    l_order_delta         NUMBER := 0;
    l_slot_date           DATE;
    l_slot_title          VARCHAR2(80) := 'Collection Slots';
    l_app_user            VARCHAR2(255) := NVL(v('APP_USER'), USER);

    TYPE t_colors IS TABLE OF VARCHAR2(20) INDEX BY PLS_INTEGER;
    l_colors t_colors;

    FUNCTION esc(p_value IN VARCHAR2) RETURN VARCHAR2 IS
    BEGIN
        RETURN htf.escape_sc(NVL(p_value, ''));
    END;

    FUNCTION ago(p_date IN DATE) RETURN VARCHAR2 IS
        l_minutes NUMBER;
        l_hours   NUMBER;
        l_days    NUMBER;
    BEGIN
        IF p_date IS NULL THEN
            RETURN '';
        END IF;

        l_minutes := FLOOR((SYSDATE - p_date) * 24 * 60);

        IF l_minutes < 1 THEN
            RETURN 'now';
        ELSIF l_minutes < 60 THEN
            RETURN l_minutes || 'm ago';
        END IF;

        l_hours := FLOOR(l_minutes / 60);
        IF l_hours < 24 THEN
            RETURN l_hours || 'h ago';
        END IF;

        l_days := FLOOR(l_hours / 24);
        RETURN l_days || 'd ago';
    END;

    FUNCTION initials(p_name IN VARCHAR2) RETURN VARCHAR2 IS
        l_name VARCHAR2(200) := REGEXP_REPLACE(TRIM(NVL(p_name, '?')), '\s+', ' ');
        l_pos  PLS_INTEGER;
    BEGIN
        l_pos := INSTR(l_name, ' ');
        IF l_pos > 0 THEN
            RETURN UPPER(SUBSTR(l_name, 1, 1) || SUBSTR(SUBSTR(l_name, l_pos + 1), 1, 1));
        END IF;

        RETURN UPPER(SUBSTR(l_name, 1, 2));
    END;

    PROCEDURE print_kpi(
        p_theme  IN VARCHAR2,
        p_icon   IN VARCHAR2,
        p_label  IN VARCHAR2,
        p_value  IN VARCHAR2,
        p_change IN VARCHAR2,
        p_up     IN BOOLEAN DEFAULT TRUE
    ) IS
    BEGIN
        htp.p('<div class="chd-kpi chd-kpi-' || esc(p_theme) || '">');
        htp.p('  <div class="chd-kpi-label"><i class="ti ti-' || esc(p_icon) || '"></i> ' || esc(p_label) || '</div>');
        htp.p('  <div class="chd-kpi-val">' || esc(p_value) || '</div>');
        htp.p('  <div class="chd-kpi-change ' || CASE WHEN p_up THEN 'chd-up' ELSE 'chd-dn' END || '"><i class="ti ti-' || CASE WHEN p_up THEN 'trending-up' ELSE 'alert-triangle' END || '"></i> ' || esc(p_change) || '</div>');
        htp.p('</div>');
    END;
BEGIN
    l_colors(1) := '#60A5FA';
    l_colors(2) := '#34D399';
    l_colors(3) := '#A78BFA';
    l_colors(4) := '#FBBF24';
    l_colors(5) := '#F87171';

    SELECT COUNT(*),
           SUM(CASE WHEN UPPER(NVL(status, 'ACTIVE')) = 'ACTIVE' THEN 1 ELSE 0 END),
           SUM(CASE WHEN UPPER(NVL(status, 'ACTIVE')) = 'ACTIVE'
                     AND NVL(stock_quantity, 0) <= 5 THEN 1 ELSE 0 END),
           SUM(CASE WHEN UPPER(NVL(status, 'ACTIVE')) = 'ACTIVE'
                     AND NVL(stock_quantity, 0) <= 0 THEN 1 ELSE 0 END)
      INTO l_total_products, l_active_products, l_low_stock_items, l_out_of_stock_items
      FROM product;

    SELECT COUNT(*),
           SUM(CASE WHEN UPPER(NVL(t.status, 'ACTIVE')) = 'ACTIVE'
                     AND UPPER(NVL(su.status, 'ACTIVE')) = 'ACTIVE'
                    THEN 1 ELSE 0 END)
      INTO l_total_traders, l_active_traders
      FROM trader t
      JOIN system_user su ON su.user_id = t.user_id;

    SELECT COUNT(*)
      INTO l_orders_today
      FROM orders
     WHERE TRUNC(order_date) = TRUNC(SYSDATE);

    SELECT COUNT(*)
      INTO l_orders_yesterday
      FROM orders
     WHERE TRUNC(order_date) = TRUNC(SYSDATE - 1);

    l_order_delta := l_orders_today - l_orders_yesterday;

    SELECT MIN(collection_date)
      INTO l_slot_date
      FROM collection_slot
     WHERE collection_date >= TRUNC(SYSDATE);

    IF l_slot_date IS NOT NULL THEN
        l_slot_title := 'Collection Slots - ' || TO_CHAR(l_slot_date, 'Dy DD Mon');
    END IF;

    htp.p('<link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">');
    htp.p('<link href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@latest/tabler-icons.min.css" rel="stylesheet">');

    htp.p('<div class="chd-db">');

    htp.p('<div class="chd-header">');
    htp.p('  <div>');
    htp.p('    <div class="chd-title">Marketplace Overview</div>');
    htp.p('    <div class="chd-sub">Cleckhuddesfax &middot; West Yorkshire &middot; Admin</div>');
    htp.p('  </div>');
    htp.p('  <div class="chd-meta"><span class="chd-dot"></span> Live &middot; ' || esc(TO_CHAR(SYSDATE, 'DD Mon YYYY HH24:MI')) || '</div>');
    htp.p('</div>');

    htp.p('<div class="chd-kpi-row">');
    print_kpi('blue', 'package', 'Total Products', TO_CHAR(l_total_products), TO_CHAR(l_active_products) || ' active now', TRUE);
    print_kpi('teal', 'users', 'Active Traders', TO_CHAR(l_active_traders), TO_CHAR(l_total_traders) || ' total traders', TRUE);
    print_kpi('amber', 'shopping-cart', 'Orders Today', TO_CHAR(l_orders_today), CASE WHEN l_order_delta >= 0 THEN '+' || l_order_delta ELSE TO_CHAR(l_order_delta) END || ' vs yesterday', l_order_delta >= 0);
    print_kpi('red', 'alert-triangle', 'Low Stock Items', TO_CHAR(l_low_stock_items), CASE WHEN l_low_stock_items = 0 THEN 'all stocked' ELSE TO_CHAR(l_out_of_stock_items) || ' out of stock' END, l_low_stock_items = 0);
    htp.p('</div>');

    htp.p('<div class="chd-mid-row">');
    htp.p('<div class="chd-panel">');
    htp.p('<div class="chd-panel-title"><i class="ti ti-chart-bar"></i> Products by Category</div>');
    htp.p('<div class="chd-cat-list">');

    DECLARE
        l_rows NUMBER := 0;
    BEGIN
        FOR r IN (
            SELECT category_name,
                   product_count,
                   CASE WHEN max_count > 0 THEN ROUND((product_count / max_count) * 100) ELSE 0 END AS pct,
                   rn
              FROM (
                    SELECT category_name,
                           product_count,
                           MAX(product_count) OVER () AS max_count,
                           ROW_NUMBER() OVER (ORDER BY product_count DESC, category_name) AS rn
                      FROM (
                            SELECT c.category_name,
                                   COUNT(p.product_id) AS product_count
                              FROM category c
                              LEFT JOIN product p
                                ON p.category_id = c.category_id
                               AND UPPER(NVL(p.status, 'ACTIVE')) = 'ACTIVE'
                             GROUP BY c.category_name
                           )
                   )
             WHERE rn <= 5
             ORDER BY rn
        ) LOOP
            l_rows := l_rows + 1;
            htp.p('<div class="chd-cat-row">');
            htp.p('  <div class="chd-cat-dot" style="background:' || l_colors(MOD(r.rn - 1, 5) + 1) || '"></div>');
            htp.p('  <div class="chd-cat-name">' || esc(r.category_name) || '</div>');
            htp.p('  <div class="chd-cat-bar-wrap"><div class="chd-cat-bar" style="width:' || r.pct || '%;background:' || l_colors(MOD(r.rn - 1, 5) + 1) || '"></div></div>');
            htp.p('  <div class="chd-cat-count">' || r.product_count || '</div>');
            htp.p('</div>');
        END LOOP;

        IF l_rows = 0 THEN
            htp.p('<div class="chd-empty">No category data yet.</div>');
        END IF;
    END;

    htp.p('</div>');
    htp.p('</div>');

    htp.p('<div class="chd-panel">');
    htp.p('<div class="chd-panel-title"><i class="ti ti-store"></i> Traders</div>');
    htp.p('<div class="chd-trader-list">');

    DECLARE
        l_rows NUMBER := 0;
    BEGIN
        FOR r IN (
            SELECT *
              FROM (
                    SELECT t.user_id,
                           NVL(t.business_name, su.name) AS trader_name,
                           NVL(MIN(s.shop_name), 'No shop yet') AS shop_label,
                           COUNT(DISTINCT p.product_id) AS product_count,
                           CASE WHEN su.created_at >= ADD_MONTHS(TRUNC(SYSDATE), -1) THEN 'New' ELSE 'Active' END AS badge
                      FROM trader t
                      JOIN system_user su ON su.user_id = t.user_id
                      LEFT JOIN shop s ON s.trader_id = t.user_id
                      LEFT JOIN product p
                        ON p.shop_id = s.shop_id
                       AND UPPER(NVL(p.status, 'ACTIVE')) = 'ACTIVE'
                     WHERE UPPER(NVL(t.status, 'ACTIVE')) = 'ACTIVE'
                       AND UPPER(NVL(su.status, 'ACTIVE')) = 'ACTIVE'
                     GROUP BY t.user_id, NVL(t.business_name, su.name), su.created_at
                     ORDER BY product_count DESC, trader_name
                   )
             WHERE ROWNUM <= 5
        ) LOOP
            l_rows := l_rows + 1;
            htp.p('<div class="chd-trader-row">');
            htp.p('  <div class="chd-avatar">' || esc(initials(r.trader_name)) || '</div>');
            htp.p('  <div class="chd-trader-main"><div class="chd-trader-name">' || esc(r.trader_name) || '</div><div class="chd-trader-cat">' || esc(r.shop_label) || ' &middot; ' || r.product_count || ' products</div></div>');
            htp.p('  <div class="chd-badge ' || CASE WHEN r.badge = 'New' THEN 'chd-badge-new' ELSE 'chd-badge-active' END || '">' || esc(r.badge) || '</div>');
            htp.p('</div>');
        END LOOP;

        IF l_rows = 0 THEN
            htp.p('<div class="chd-empty">No active traders yet.</div>');
        END IF;
    END;

    htp.p('</div>');
    htp.p('</div>');
    htp.p('</div>');

    htp.p('<div class="chd-bottom-row">');

    htp.p('<div class="chd-panel">');
    htp.p('<div class="chd-panel-title"><i class="ti ti-clock"></i> Recent Activity</div>');
    htp.p('<div class="chd-act-list">');

    DECLARE
        l_rows NUMBER := 0;
        l_icon_class VARCHAR2(30);
        l_icon VARCHAR2(30);
    BEGIN
        FOR r IN (
            SELECT *
              FROM (
                    SELECT activity_text, activity_date, activity_kind
                      FROM (
                            SELECT 'Order #' || order_id || ' ' || LOWER(status) AS activity_text,
                                   order_date AS activity_date,
                                   CASE
                                       WHEN UPPER(status) IN ('CANCELLED', 'CANCELED') THEN 'order_bad'
                                       WHEN UPPER(status) IN ('COMPLETED', 'PAID', 'CONFIRMED') THEN 'order_good'
                                       ELSE 'order_neutral'
                                   END AS activity_kind
                              FROM orders
                             WHERE order_date IS NOT NULL
                            UNION ALL
                            SELECT NVL(t.business_name, su.name) || ' joined as trader',
                                   su.created_at,
                                   'trader_new'
                              FROM trader t
                              JOIN system_user su ON su.user_id = t.user_id
                             WHERE su.created_at IS NOT NULL
                            UNION ALL
                            SELECT product_name || ' stock is low',
                                   SYSDATE,
                                   'low_stock'
                              FROM product
                             WHERE UPPER(NVL(status, 'ACTIVE')) = 'ACTIVE'
                               AND NVL(stock_quantity, 0) <= 5
                           )
                     ORDER BY activity_date DESC NULLS LAST
                   )
             WHERE ROWNUM <= 5
        ) LOOP
            l_rows := l_rows + 1;
            l_icon_class := CASE r.activity_kind
                                WHEN 'order_good' THEN 'chd-ai-green'
                                WHEN 'order_bad' THEN 'chd-ai-red'
                                WHEN 'low_stock' THEN 'chd-ai-amber'
                                WHEN 'trader_new' THEN 'chd-ai-blue'
                                ELSE 'chd-ai-blue'
                            END;
            l_icon := CASE r.activity_kind
                          WHEN 'order_good' THEN 'check'
                          WHEN 'order_bad' THEN 'x'
                          WHEN 'low_stock' THEN 'alert-triangle'
                          WHEN 'trader_new' THEN 'user-plus'
                          ELSE 'package'
                      END;
            htp.p('<div class="chd-act-row">');
            htp.p('  <div class="chd-act-icon ' || l_icon_class || '"><i class="ti ti-' || l_icon || '"></i></div>');
            htp.p('  <div class="chd-act-text">' || esc(r.activity_text) || '</div>');
            htp.p('  <div class="chd-act-time">' || esc(ago(r.activity_date)) || '</div>');
            htp.p('</div>');
        END LOOP;

        IF l_rows = 0 THEN
            htp.p('<div class="chd-empty">No recent activity yet.</div>');
        END IF;
    END;

    htp.p('</div>');
    htp.p('</div>');

    htp.p('<div class="chd-panel">');
    htp.p('<div class="chd-panel-title"><i class="ti ti-map-pin"></i> ' || esc(l_slot_title) || '</div>');
    htp.p('<div class="chd-slot-grid">');

    DECLARE
        l_rows NUMBER := 0;
        l_remaining NUMBER;
        l_slot_class VARCHAR2(30);
        l_status_class VARCHAR2(30);
        l_status_text VARCHAR2(30);
    BEGIN
        IF l_slot_date IS NOT NULL THEN
            FOR r IN (
                SELECT cs.collection_time,
                       COUNT(o.order_id) AS booked_count
                  FROM collection_slot cs
                  LEFT JOIN orders o ON o.slot_id = cs.slot_id
                 WHERE cs.collection_date = l_slot_date
                 GROUP BY cs.slot_id, cs.collection_time
                 ORDER BY cs.collection_time
            ) LOOP
                l_rows := l_rows + 1;
                l_remaining := GREATEST(c_slot_capacity - r.booked_count, 0);

                IF l_remaining = 0 THEN
                    l_slot_class := 'chd-slot-taken';
                    l_status_class := 'chd-s-taken';
                    l_status_text := 'Full';
                ELSIF l_remaining <= 3 THEN
                    l_slot_class := 'chd-slot-few';
                    l_status_class := 'chd-s-few';
                    l_status_text := l_remaining || ' left';
                ELSE
                    l_slot_class := 'chd-slot-open';
                    l_status_class := 'chd-s-open';
                    l_status_text := l_remaining || ' left';
                END IF;

                htp.p('<div class="chd-slot ' || l_slot_class || '">');
                htp.p('  <div class="chd-slot-time">' || esc(r.collection_time) || '</div>');
                htp.p('  <div class="chd-slot-status ' || l_status_class || '">' || esc(l_status_text) || '</div>');
                htp.p('</div>');
            END LOOP;
        END IF;

        IF l_rows = 0 THEN
            htp.p('<div class="chd-empty">No upcoming slots found.</div>');
        END IF;
    END;

    htp.p('</div>');
    htp.p('</div>');

    htp.p('<div class="chd-panel">');
    htp.p('<div class="chd-panel-title"><i class="ti ti-server"></i> System Status</div>');
    htp.p('<div class="chd-sys-list">');
    htp.p('<div class="chd-sys-row"><div class="chd-sys-label"><i class="ti ti-database"></i> Oracle DB</div><div class="chd-sys-val chd-ok">Connected</div></div>');
    htp.p('<div class="chd-divider"></div>');
    htp.p('<div class="chd-sys-row"><div class="chd-sys-label"><i class="ti ti-app-window"></i> APEX App</div><div class="chd-sys-val chd-ok">Running</div></div>');
    htp.p('<div class="chd-divider"></div>');
    htp.p('<div class="chd-sys-row"><div class="chd-sys-label"><i class="ti ti-refresh"></i> Last Sync</div><div class="chd-sys-val">' || esc(TO_CHAR(SYSDATE, 'HH24:MI')) || '</div></div>');
    htp.p('<div class="chd-divider"></div>');
    htp.p('<div class="chd-sys-row"><div class="chd-sys-label"><i class="ti ti-user"></i> Session</div><div class="chd-sys-val">' || esc(l_app_user) || '</div></div>');
    htp.p('<div class="chd-divider"></div>');
    htp.p('<div class="chd-sys-row"><div class="chd-sys-label"><i class="ti ti-lock"></i> Auth</div><div class="chd-sys-val chd-ok">Verified</div></div>');
    htp.p('</div>');
    htp.p('</div>');

    htp.p('</div>');
    htp.p('</div>');
END;
