<?php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('twentytwentyfive-style', get_template_directory_uri() . '/style.css');
});

// no need of cpt ui and added in main menu, now use ACF
add_action('init', function () {
    register_post_type('product', [
        'labels' => [
            'name' => 'Products',
            'singular_name' => 'Product'
        ],
        'public' => true,
        'has_archive' => true,
        'menu_icon' => 'dashicons-cart',
        'supports' => ['title', 'thumbnail', 'editor'],
        'show_in_rest' => true,
    ]);
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
        echo '<a href="' . site_url('/all-tasks') . '" class="button" style="margin-right:10px;">View All Tasks</a>';
        echo '<a href="' . site_url('/product-listing') . '" class="button">Products List</a>';
        echo '</div>';

        return ob_get_clean();
    }

    return $content;
}
add_filter('the_content', 'show_manager_form_on_dashboard');

function show_manager_task_form($content) {
    if (is_page('create-task') && (current_user_can('administrator') || current_user_can('manager'))) {
        ob_start();

        // Prefill ACF image field from image URL
        if (!empty($_GET['image'])) {
            $image_url = esc_url_raw($_GET['image']);
            $attachment_id = attachment_url_to_postid($image_url); // Converts URL to ID
            if ($attachment_id) {
                $_POST['acf']['item_photo'] = $attachment_id;
            }
        }

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
            $thumb_url = $thumb ? esc_url($thumb['sizes']['medium']) : '';
            $ring_size = get_field('ring_size');
            $chain_size = get_field('chain_length');
            $express = get_field('express');
            $replacement = get_field('replacement');
            $product_type = get_field('product_type');
        
            echo '<li style="margin-bottom:20px; list-style:none; display:flex; align-items:flex-start;">';
        
            if ($thumb_url) {
                echo '<img src="' . $thumb_url . '" alt="" style="width:65px; height:65px; object-fit:cover; margin-right:15px; border-radius:6px;" />';
            }
        
            echo '<div>';
            echo '<a href="' . get_permalink() . '" style="text-decoration:none; color:#111; font-size:15px; font-weight:500;">' . wp_trim_words(get_the_title(), 10, '...') . '</a>';
        
            echo '<div style="font-size:13px; color:#555; margin-top:4px; display:flex; align-items:center; gap:10px;">';
            // echo 'Ring Size: ' . esc_html($ring_size);
            
            if ($product_type === 'Ring' && $ring_size) {
                echo 'Ring Size: ' . esc_html($ring_size);
            } elseif ($product_type === 'Chain' && $chain_size) {
                echo 'Chain Size: ' . esc_html($chain_size);
            }
        
        
            if ($express) {
                echo '<img src="https://deeppink-rook-298525.hostingersite.com/wp-content/uploads/2025/07/express-delivery.png" title="Express Delivery" style="height:15px; vertical-align:middle;" />';
            }
        
            if ($replacement) {
                echo '<img src="https://deeppink-rook-298525.hostingersite.com/wp-content/uploads/2025/07/exchange.png" title="Replacement" style="height:15px; vertical-align:middle;" />';
            }
        
            echo '</div>'; // end subinfo
            echo '</div>'; // end text block
        
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
            echo '<img src="' . esc_url($image['url']) . '" alt="" style="max-width:150px; margin-bottom:15px; cursor:pointer;" onclick="openLightbox(this.src)" />';
        }
        echo '
        <div id="lightbox" style="display:none; position:fixed; top:0; left:0; width:100%; height:100%; background-color:rgba(0,0,0,0.8); z-index:9999; justify-content:center; align-items:center;" onclick="closeLightbox()">
          <img id="lightbox-img" src="" style="max-width:90%; max-height:90%;" />
        </div>
        <script>
          function openLightbox(src) {
            document.getElementById("lightbox-img").src = src;
            document.getElementById("lightbox").style.display = "flex";
          }
          function closeLightbox() {
            document.getElementById("lightbox").style.display = "none";
          }
        </script>';

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



// REST API for WooCommerce Products
add_action('rest_api_init', function () {
  register_rest_route('custom/v1', '/products', [
    'methods' => 'GET',
    'callback' => 'get_custom_products',
  ]);
});

function get_custom_products($request) {
  $search = sanitize_text_field($request['search']);
  $category = sanitize_text_field($request['category']);

  $args = [
    'post_type' => 'product',
    'posts_per_page' => -1,
    's' => $search,
    'tax_query' => [],
  ];

  if ($category) {
    $args['tax_query'][] = [
      'taxonomy' => 'product_cat',
      'field' => 'slug',
      'terms' => $category,
    ];
  }

  $query = new WP_Query($args);
  $products = [];

  foreach ($query->posts as $p) {
    $products[] = [
      'id' => $p->ID,
      'title' => get_the_title($p->ID),
      'image' => get_the_post_thumbnail_url($p->ID, 'medium'),
    ];
  }

  return $products;
}

// Shortcode to render product search/filter UI
// Update the product browser shortcode to include category
add_shortcode('product_browser', function () {
    ob_start(); ?>
    <div>
        <input type="text" id="search" placeholder="Search..." style="padding:8px; margin-bottom:10px;">
        <select id="category" style="padding:8px; margin-bottom:10px;">
            <option value="">All Categories</option>
            <?php 
            $terms = get_terms(['taxonomy' => 'product_cat', 'hide_empty' => false]);
            foreach ($terms as $term) {
                echo '<option value="' . esc_attr($term->slug) . '">' . esc_html($term->name) . '</option>';
            }
            ?>
        </select>
        <div id="product-list" style="display: grid; gap: 15px; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); margin-top:20px;"></div>
    </div>

    <script>
    async function loadProducts() {
        const search = document.getElementById('search').value;
        const category = document.getElementById('category').value;
        const res = await fetch(`/wp-json/custom/v1/products?search=${encodeURIComponent(search)}&category=${encodeURIComponent(category)}`);
        const products = await res.json();
        const list = document.getElementById('product-list');
        
        list.innerHTML = products.map(p => `
            <a href="/create-task?title=${encodeURIComponent(p.title)}&image=${encodeURIComponent(p.image)}&category=${encodeURIComponent(category)}" 
               style="text-decoration:none; color:#333; display:block; background:#f9f9f9; padding:15px; border-radius:8px; transition:transform 0.2s;"
               onmouseover="this.style.transform='translateY(-5px)'" 
               onmouseout="this.style.transform='none'">
                <img src="${p.image}" style="width:100%; height:150px; object-fit:contain; margin-bottom:10px; background:#fff; border-radius:4px;">
                <div style="text-align:center; font-size:14px;">${p.title}</div>
            </a>
        `).join('');
    }

    // Debounce the search input
    let debounceTimer;
    document.getElementById('search').addEventListener('input', () => {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(loadProducts, 300);
    });
    
    document.getElementById('category').addEventListener('change', loadProducts);
    loadProducts();
    </script>
    <?php
    return ob_get_clean();
});


// to prefill the images
// add_filter('acf/load_field/name=item_photo', function($field) {
//     if (is_page('create-task') && isset($_GET['image'])) {
//         $image_url = esc_url_raw($_GET['image']);
//         $attachment_id = attachment_url_to_postid($image_url);

//         if ($attachment_id) {
//             $field['value'] = $attachment_id;
//         }
//     }
//     return $field;
// });
add_filter('acf/load_field/name=item_photo', function($field) {
    if (is_page('create-task') && isset($_GET['image'])) {
        $image_url = esc_url_raw($_GET['image']);
        $image_url = preg_replace('/-\d+x\d+(?=\.\w{3,4}$)/', '', $image_url); // Strip size

        $attachment_id = attachment_url_to_postid($image_url);

        if ($attachment_id) {
            $field['value'] = $attachment_id;
        }
    }
    return $field;
});

// Add this to prefill the title
add_filter('acf/prepare_field/name=_post_title', function($field) {
    if (is_page('create-task') && isset($_GET['title'])) {
        $field['value'] = sanitize_text_field($_GET['title']);
    }
    return $field;
});

