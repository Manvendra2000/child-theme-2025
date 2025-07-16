<?php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('twentytwentyfive-style', get_template_directory_uri() . '/style.css');
});

add_action('admin_menu', function () {
    add_menu_page(
        'Tasks',
        'Tasks',
        'edit_posts',
        'edit.php?post_type=task',
        '',
        'dashicons-list-view',
        6
    );
});

add_action('wp_head', 'acf_form_head', 1);

function custom_login_redirect($redirect_to, $request, $user) {
    if (isset($user->roles) && is_array($user->roles)) {
        if (in_array('administrator', $user->roles)) {
            return site_url('/admin-panel');
        } elseif (in_array('worker', $user->roles)) {
            return site_url('/worker-dashboard');
        } elseif (in_array('tester', $user->roles)) {
            return site_url('/testing-review');
        } elseif (in_array('manager', $user->roles)) {
            return site_url('/manager-dashboard');
        }
    }
    return $redirect_to;
}
add_filter('login_redirect', 'custom_login_redirect', 10, 3);

function restrict_pages_by_role() {
    if (is_page('worker-dashboard') && !current_user_can('worker')) {
        wp_redirect(home_url());
        exit;
    }
    if (is_page('admin-panel') && !current_user_can('administrator')) {
        wp_redirect(home_url());
        exit;
    }
    if (is_page('testing-review') && !current_user_can('tester')) {
        wp_redirect(home_url());
        exit;
    }
    if (is_page('manager-dashboard') && !current_user_can('manager')) {
        wp_redirect(home_url());
        exit;
    }
}
add_action('template_redirect', 'restrict_pages_by_role');


//  Hide Admin Bar for Non-Admins
add_action('after_setup_theme', function () {
    if (!current_user_can('administrator') && !is_admin()) {
        show_admin_bar(false);
    }
});


// Block Dashboard Access for Non-Admins
add_action('admin_init', function () {
    if (!current_user_can('administrator') && !wp_doing_ajax()) {
        wp_redirect(home_url());
        exit;
    }
});


function show_manager_form_on_dashboard($content) {
    if (is_page('manager-dashboard') && (current_user_can('administrator') || current_user_can('manager'))) {
        ob_start();

       echo '<h1>Manager Dashboard</h1>';
        echo '<div style="margin-bottom: 20px;">';
        echo '<a href="' . site_url('/create-task') . '" class="button" style="margin-right:10px;">Create New Task</a>';
        echo '<a href="' . site_url('/all-tasks') . '" class="button">View All Tasks</a>';
        echo '</div>';

        return ob_get_clean();
    }

    return $content;
}
add_filter('the_content', 'show_manager_form_on_dashboard');
function show_manager_task_form($content) {
    if (is_page('create-task') && (current_user_can('administrator') || current_user_can('manager'))) {
        ob_start();

        echo '<h2>Create a New Task</h2>';
        echo '<nav style="font-size:14px; color:#666;">
             <span style="color:#999;">&#8592;</span>
                <a href="javascript:history.back()" style="color:#666; text-decoration:none;" onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">
                    Go Back
                </a>
              </nav>';
        acf_form([
            'post_id' => 'new_post',
            'post_title' => true,
            'post_content' => false,
            'new_post' => [
                'post_type' => 'task',
                'post_status' => 'publish'
            ],
            'submit_value' => 'Create Task'
        ]);

        return ob_get_clean();
    }

    return $content;
}
add_filter('the_content', 'show_manager_task_form');

function show_all_tasks_for_manager($content) {
    if (is_page('all-tasks') && (current_user_can('administrator') || current_user_can('manager'))) {
        ob_start();

        echo '<h2>All Tasks</h2>';
        echo '<nav style="font-size:14px; color:#666;">
                <span style="color:#999;">&#8592;</span>
                <a href="javascript:history.back()" style="color:#666; text-decoration:none;" onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">
                    Go Back
                </a>
              </nav>';

        $statuses = ['In-house', 'Worker', 'Testing', 'Done'];

        foreach ($statuses as $status) {
            $query = new WP_Query([
                'post_type' => 'task',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [[
                    'key' => 'status',
                    'value' => $status,
                    'compare' => '='
                ]]
            ]);

           if ($query->have_posts()) {
                echo "<h3>$status</h3><ul style='padding-left:0;'>";
            
                while ($query->have_posts()) {
                    $query->the_post();
                    $thumb = get_field('item_photo');
                    $thumb_url = $thumb ? esc_url($thumb['sizes']['thumbnail']) : '';
                    $task_status = get_field('status');
            
                    echo '<li style="margin-bottom:15px; list-style:none; display:flex; align-items:center;">';
            
                    if ($thumb_url) {
                        echo '<img src="' . $thumb_url . '" alt="" style="width:50px; height:50px; object-fit:cover; margin-right:10px; border-radius:4px;" />';
                    }
            
                    echo '<div>';
                    echo '<a href="' . get_permalink() . '" style="text-decoration:none; color:#333; font-size:14px;">' . wp_trim_words(get_the_title(), 10, '...') . '</a>';
                    echo '<div style="font-size:12px; color:#888;">Status: ' . esc_html($task_status) . '</div>';
                    echo '</div>';
            
                    echo '</li>';
                }
            
                echo '</ul>';
            }

            wp_reset_postdata();
        }

        return ob_get_clean();
    }

    return $content;
}
add_filter('the_content', 'show_all_tasks_for_manager');

add_action('init', function () {
    if (is_page(['create-task', 'all-tasks']) || is_singular('task')) {
        acf_form_head();
    }
});

add_action('init', function() {
    if (is_page() || is_singular('task')) {
        acf_form_head();
    }
});


add_shortcode('manager_form', function() {
    ob_start();
    if (current_user_can('administrator') || current_user_can('manager')) {
        acf_form([
            'post_id' => 'new_post',
            'new_post' => ['post_type' => 'task', 'post_status' => 'publish'],
            'submit_value' => 'Create Task'
        ]);
    } else {
        echo 'Not allowed.';
    }
    return ob_get_clean();
});



// worker parent_dropdown
add_action('the_content', 'show_worker_tasks_on_dashboard');

function show_worker_tasks_on_dashboard($content) {
    if (is_page('worker-dashboard') && current_user_can('worker')) {
        $current_user_id = get_current_user_id();

        $statuses = ['In-house', 'Worker', 'Testing', 'Done'];

        ob_start();

        echo '<h2>Your Tasks by Status</h2>';

        foreach ($statuses as $status) {
            $args = [
                'post_type' => 'task',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => 'assigned_to',
                        'value' => $current_user_id,
                        'compare' => '='
                    ],
                    [
                        'key' => 'status',
                        'value' => $status,
                        'compare' => '='
                    ]
                ]
            ];

            $query = new WP_Query($args);

            if ($query->have_posts()) {
            echo "<h3>$status</h3><ul style='padding-left:0;'>";
        
            while ($query->have_posts()) {
                $query->the_post();
                $thumb = get_field('item_photo');
                $thumb_url = $thumb ? esc_url($thumb['sizes']['thumbnail']) : '';
                $ring_size = get_field('ring_size');
        
                echo '<li style="margin-bottom:15px; list-style:none; display:flex; align-items:center;">';
        
                if ($thumb_url) {
                    echo '<img src="' . $thumb_url . '" alt="" style="width:50px; height:50px; object-fit:cover; margin-right:10px; border-radius:4px;" />';
                }
        
                echo '<div>';
                echo '<a href="' . get_permalink() . '" style="text-decoration:none; color:#333; font-size:14px;">' . wp_trim_words(get_the_title(), 10, '...') . '</a>';
                echo '<div style="font-size:12px; color:#888;">Ring Size: ' . esc_html($ring_size) . '</div>';
                echo '</div>';
        
                echo '</li>';
            }
        
            echo '</ul>';
        }
            wp_reset_postdata();
        }

        return ob_get_clean();
    }

    return $content;
}

// on the respective task page: 
add_filter('the_content', 'show_task_details_page');
function show_task_details_page($content) {
    if (is_singular('task')) {
        ob_start();

        echo '<nav style="font-size:14px; color:#666;">
              <span style="color:#999;">&#8592;</span>
              <a href="javascript:history.back()" style="color:#666; text-decoration:none;" onmouseover="this.style.textDecoration=\'underline\'" onmouseout="this.style.textDecoration=\'none\'">
                Go Back
                </a>
              </nav>';
        echo '<div style="border:1px solid #ccc;padding:20px;">';
        echo '<h2>' . get_the_title() . '</h2>';

        $image = get_field('item_photo');
        if ($image) {
            echo '<img src="' . esc_url($image['url']) . '" alt="" style="max-width:150px;margin-bottom:15px;" />';
        }

        echo '<p><strong>Ring Size:</strong> ' . get_field('ring_size') . '</p>';
        echo '<p><strong>Plating Type:</strong> ' . get_field('plating_type') . '</p>';
        echo '<p><strong>Plating Thickness:</strong> ' . get_field('plating_thickness') . '</p>';
        echo '<p><strong>Express:</strong> ' . (get_field('express') ? 'Yes' : 'No') . '</p>';
        echo '<p><strong>Replacement:</strong> ' . (get_field('replacement') ? 'Yes' : 'No') . '</p>';
        echo '<p><strong>Status:</strong> ' . get_field('status') . '</p>';
        echo '<p><strong>Admin Remark:</strong> ' . get_field('add_remark') . '</p>';

        echo '</div>';
        echo '<h3>Update Status</h3>';
        acf_form([
            'post_id' => get_the_ID(),
            'fields' => ['status'],
            'submit_value' => 'Update Task',
        ]);

        return ob_get_clean();
    }

    return $content;
}
add_action('init', function () {
    $role = get_role('worker');
    if ($role && !$role->has_cap('edit_posts')) {
        $role->add_cap('edit_posts');
        $role->add_cap('edit_published_posts');
        $role->add_cap('edit_others_posts'); // Optional, if workers can edit any task
    }
});

add_filter('acf/pre_save_post', function($post_id) {
    if (get_post_type($post_id) === 'task' && current_user_can('worker')) {
        return $post_id;
    }
    return $post_id;
});


add_filter('the_content', 'worker_task_edit_form');

function worker_task_edit_form($content) {
    if (is_singular('task') && current_user_can('worker')) {
        $assigned_to = get_field('assigned_to');
        $current_user = get_current_user_id();

        if ((int)$assigned_to === $current_user) {
            ob_start();

            // Keep default details
            echo $content;

            echo '<h3>Update Task Status</h3>';

            echo '<div class="acf-form-wrapper">';
            acf_form([
                'post_id' => get_the_ID(),
                'fields' => ['status'], // make sure this matches the field name in ACF
                'submit_value' => 'Update Status',
            ]);
            echo '</div>';

            return ob_get_clean();
        }
    }

    return $content;
}

// Limit status choices for worker role only
add_filter('acf/load_field/name=status', function($field) {
    if (current_user_can('worker')) {
        $field['choices'] = [
            'Worker' => 'Worker',
            'Testing' => 'Testing',
        ];
    }
    return $field;
});



add_action('login_enqueue_scripts', function () {
    wp_enqueue_style('custom-login-css', get_stylesheet_directory_uri() . '/style.css');
});




// testing review page for tester get_defined_functions

// giving permissions
add_action('init', function () {
    $role = get_role('tester');
    if ($role && !$role->has_cap('edit_posts')) {
        $role->add_cap('edit_posts');
        $role->add_cap('edit_published_posts');
        $role->add_cap('edit_others_posts'); // allows editing tasks not authored by them
    }
});

// show status stuff
add_filter('the_content', function($content) {
    if (is_page('testing-review') && current_user_can('tester')) {
        $statuses = ['In-house', 'Worker', 'Testing', 'Done'];

        ob_start();
        echo '<h2>All Tasks by Status</h2>';

        foreach ($statuses as $status) {
            $args = [
                'post_type' => 'task',
                'post_status' => 'publish',
                'posts_per_page' => -1,
                'meta_query' => [
                    [
                        'key' => 'status',
                        'value' => $status,
                        'compare' => '='
                    ]
                ]
            ];

            $query = new WP_Query($args);

            if ($query->have_posts()) {
                echo "<h3>$status</h3><ul>";
                while ($query->have_posts()) {
                    $query->the_post();
                    echo '<li><a href="' . get_permalink() . '">' . get_the_title() . '</a></li>';
                }
                echo '</ul>';
            }
            wp_reset_postdata();
        }

        return ob_get_clean();
    }

    return $content;
    
    add_filter('the_content', function($content) {
    if (is_singular('task') && current_user_can('tester')) {
        ob_start();

        echo $content;

        echo '<h3>Tester Update Status</h3>';
        acf_form([
            'post_id' => get_the_ID(),
            'fields' => ['status'],
            'submit_value' => 'Update Status',
        ]);

        return ob_get_clean();
    }

    return $content;
});
});

add_shortcode('custom_logout', function() {
    return '<a href="' . wp_logout_url(home_url()) . '">Logout</a>';
});

// force login
// Force login for all visitors unless logged in
add_action('template_redirect', function () {
    if (
        !is_user_logged_in() &&
        !is_page('wp-login.php') &&
        !is_admin() &&
        !is_login_page() &&
        !(defined('DOING_AJAX') && DOING_AJAX)
    ) {
        wp_redirect(wp_login_url($_SERVER['REQUEST_URI']));
        exit;
    }
});

// Block REST API access for unauthenticated users
add_filter('rest_authentication_errors', function ($result) {
    if (!is_user_logged_in()) {
        return new WP_Error('rest_forbidden', 'REST API restricted to logged-in users.', ['status' => 401]);
    }
    return $result;
});

// Helper to detect login page
function is_login_page() {
    return in_array($GLOBALS['pagenow'], ['wp-login.php', 'wp-register.php']);
}