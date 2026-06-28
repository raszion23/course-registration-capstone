<?php
/**
 * Plugin Name: Course Registration Shortcodes by Kana
 * Description: Custom Shortcodes for the Course Registration System.
 * Version: 1.0
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Start a session to store user data (e.g., student ID)
if (!session_id()) {
    session_start();
}

function kana_course_plugin_enqueue_styles() {
    wp_enqueue_style(
        'shortcode-course-api-styles',
        plugin_dir_url(__FILE__) . 'css/style.css',
        array(),
        '1.0'
    );
}
add_action('wp_enqueue_scripts', 'kana_course_plugin_enqueue_styles');


// === Shortcode: Display Waitlisted Courses ===
add_shortcode('waitlisted_courses', 'waitlisted_courses_shortcode');

function waitlisted_courses_shortcode() {
    ob_start();
    ?>
    <div id="waitlisted-courses">
        <h3 style="margin-bottom: 1rem; font-family: sans-serif;">Your Waitlisted Courses</h3>
        <div id="waitlist-list">Loading...</div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            function fetchWaitlist() {
                $('#waitlist-list').html('Loading...');
                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    method: 'POST',
                    data: {
                        action: 'get_waitlisted_courses'
                    },
                    success: function(response) {
                        if (response.success && response.courses.length > 0) {
                            let html = '';

                            response.courses.forEach(course => {
				html += `
                                    <div class="course-card">
                                        <div class="course-card-content">
                                            <span class="course-department course-code-box">${course.department} ${course.course_code}</span>
                                            <div class="course-title" style="margin-top: 10px;">${course.course_name}</div>
                                            <div class="course-description">${course.description}</div>
                                            <div class="waitlist-info">Your Position: <strong>${course.waitlist_position}</strong></div>
                                        </div>
                                        <button class="remove-waitlist-btn api-btn" data-wl-id="${course.waitlist_id}">Remove from Waitlist</button>
                                    </div>`;
                            });

                            $('#waitlist-list').html(html);
                        } else {
                            $('#waitlist-list').html('<p style="font-family: sans-serif;">You are not waitlisted for any courses.</p>');
                        }
                    },
                    error: function() {
                        $('#waitlist-list').html('<p style="font-family: sans-serif;">Failed to load waitlisted courses.</p>');
                    }
                });
            }

            fetchWaitlist();

            $(document).on('click', '.remove-waitlist-btn', function() {
                const wlId = $(this).data('wl-id');
                if (!confirm('Are you sure you want to remove this course from your waitlist?')) return;

                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    method: 'POST',
                    data: {
                        action: 'remove_from_waitlist',
                        waitlist_id: wlId
                    },
                    success: function(response) {
                        alert(response.message);
                        if (response.success) {
                            fetchWaitlist();
                        }
                    },
                    error: function() {
                        alert('Error trying to remove from waitlist.');
                    }
                });
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

// === AJAX: Fetch Waitlisted Courses ===
add_action('wp_ajax_get_waitlisted_courses', 'handle_get_waitlisted_courses');
function handle_get_waitlisted_courses() {
    session_start();

    if (!isset($_SESSION['student_id'])) {
        wp_send_json(['success' => false, 'message' => 'Not logged in']);
    }

    $student_id = intval($_SESSION['student_id']);
    $response = wp_remote_get(home_url("/wp-json/course-api/v1/waitlist/{$student_id}"));

    if (is_wp_error($response)) {
        wp_send_json(['success' => false, 'message' => 'Error contacting server']);
    }

    $body = wp_remote_retrieve_body($response);
    $waitlisted_courses = json_decode($body, true);

    if (!is_array($waitlisted_courses)) {
        wp_send_json(['success' => true, 'courses' => []]);
    }

    wp_send_json(['success' => true, 'courses' => $waitlisted_courses]);
}

// === AJAX: Remove From Waitlist ===
add_action('wp_ajax_remove_from_waitlist', 'handle_remove_from_waitlist');
function handle_remove_from_waitlist() {
    session_start();

    if (!isset($_SESSION['student_id'])) {
        wp_send_json(['success' => false, 'message' => 'Not logged in']);
    }

    $waitlist_id = intval($_POST['waitlist_id'] ?? 0);
    if (!$waitlist_id) {
        wp_send_json(['success' => false, 'message' => 'Invalid waitlist ID']);
    }

    $response = wp_remote_post(home_url('/wp-json/course-api/v1/waitlist/remove'), [
        'body'    => json_encode(['waitlist_id' => $waitlist_id]),
        'headers' => ['Content-Type' => 'application/json'],
    ]);

    if (is_wp_error($response)) {
        wp_send_json(['success' => false, 'message' => 'Server error']);
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if (!empty($result['success'])) {
        wp_send_json(['success' => true, 'message' => 'Removed from waitlist']);
    } else {
        wp_send_json(['success' => false, 'message' => 'Failed to remove from waitlist']);
    }
}


function display_registration_form() {
    ob_start();

    if (!empty($_SESSION['student_id'])) {
        echo '<p>You are already registered and logged in.</p>';
        echo do_shortcode('[logout_button]');
	return ob_get_clean();
    }

    ?>
    <form id="registration-form" action="#" method="POST">
        <label for="first_name">First Name:</label>
        <input type="text" name="first_name" required><br>

        <label for="last_name">Last Name:</label>
        <input type="text" name="last_name" required><br>

        <label for="email">Email:</label>
        <input type="email" name="email" required><br>

        <label for="password">Password:</label>
        <input type="password" name="password" required><br>

        <label for="phone_number">Phone Number (optional):</label>
        <input type="text" name="phone_number"><br>

        <label for="major">Major (optional):</label>
        <input type="text" name="major"><br>

        <button class="api-btn" type="submit">Register</button>
    </form>
    <div id="registration-response"></div>

    <?php

    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
        $data = [
            'first_name' => sanitize_text_field($_POST['first_name']),
            'last_name' => sanitize_text_field($_POST['last_name']),
            'email' => sanitize_email($_POST['email']),
            'password' => sanitize_text_field($_POST['password']),
            'phone_number' => sanitize_text_field($_POST['phone_number']),
            'major' => sanitize_text_field($_POST['major']),
        ];

        $response = wp_remote_post(home_url('/wp-json/course-api/v1/register-user'), [
            'body'    => json_encode($data),
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!is_wp_error($response) && isset($result['success']) && $result['success']) {
            echo '<p>Registration successful! You can now <a href="/login-2/">log in</a>.</p>';
        } else {
            $error = is_wp_error($response) ? $response->get_error_message() : ($result['message'] ?? 'Registration failed.');
            echo '<p style="color:red;">' . esc_html($error) . '</p>';
        }
    }

    return ob_get_clean();
}
add_shortcode('registration_form', 'display_registration_form');


add_shortcode('cart_dropdown', 'kana_cart_dropdown_shortcode');
function kana_cart_dropdown_shortcode() {
    $student_id = intval($_SESSION['student_id']);

    ob_start(); ?>
    <div id="dropdown-cart-container" style="position: relative; display: inline-block;">
        <button id="dropdown-cart-toggle" class="api-btn">
            🛒 My Cart <span id="dropdown-cart-count" style="background:red;color:white;border-radius:50%;padding:2px 6px;font-size:0.8rem;margin-left:6px;">0</span>
        </button>

        <div id="dropdown-cart-panel" style="display:none;position:absolute;top:100%;right:0;z-index:1000;background:#fff;border:1px solid #ccc;border-radius:12px;padding:16px;width:350px;box-shadow:0 4px 12px rgba(0,0,0,0.1);">
            <div id="dropdown-cart-content">Loading...</div>
            <button id="register-dropdown-btn" class="api-btn" style="margin-top: 10px;">Register for All Courses</button>
            <div id="dropdown-registration-summary" style="margin-top: 10px;"></div>
        </div>
    </div>

    <script>
    (function($) {
        const studentId = <?php echo json_encode($student_id); ?>;

        function fetchDropdownCart(updatePanel = false) {
            $.get('/wp-json/course-api/v1/cart/' + studentId, function(data) {
                const $panel = $('#dropdown-cart-content');
                const $count = $('#dropdown-cart-count');

                if (!data || data.length === 0) {
                    if (updatePanel) $panel.html('<p>Your cart is empty.</p>');
                    $count.text('0');
                    return;
                }

                $count.text(data.length);

                if (updatePanel) {
                    const html = data.map(course => `
                        <div style="margin-bottom: 10px;">
                            <strong>${course.department} ${course.course_code}: ${course.course_name}</strong><br>
                            ${course.description}<br>
                            <em>Available Seats: ${course.capacity - course.seats_filled}</em><br>
                            <button class="api-btn remove-from-cart-btn" data-cart-id="${course.cart_id}">Remove</button>
                        </div>
                        <hr>
                    `).join('');
                    $panel.html(html);
                }
            });
        }

        function registerForCoursesFromDropdown() {
            $.get('/wp-json/course-api/v1/cart/' + studentId, function(courses) {
                if (!courses || courses.length === 0) {
                    alert('No courses to register for!');
                    return;
                }

                let successful = [];
                let failed = [];

                const registerNext = (index) => {
                    if (index >= courses.length) {
                        fetchDropdownCart(true);
                        document.dispatchEvent(new Event('cart-updated'));

                        const summary = `
                            <h4>Registration Summary</h4>
                            <p>✅ Registered: ${successful.length}</p>
                            <ul>${successful.map(c => `<li>${c}</li>`).join('')}</ul>
                            ${failed.length > 0 ? `<p>❌ Failed: ${failed.length}</p><ul>${failed.map(c => `<li>${c}</li>`).join('')}</ul>` : ''}
                        `;
                        $('#dropdown-registration-summary').html(summary);
                        return;
                    }

                    const course = courses[index];
                    const available = (course.capacity || 0) - (course.seats_filled || 0);

                    if (available <= 0) {
                        failed.push(`${course.course_code}: ${course.course_name} (Full)`);
                        registerNext(index + 1);
                        return;
                    }

                    $.ajax({
                        url: '/wp-json/course-api/v1/register',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            student_id: studentId,
                            course_id: course.course_id
                        }),
                        success: function() {
                            successful.push(`${course.course_code}: ${course.course_name}`);
                            registerNext(index + 1);
                        },
                        error: function(jqXHR) {
                            const msg = jqXHR.responseJSON?.message || 'Error';
                            failed.push(`${course.course_code}: ${course.course_name} (${msg})`);
                            registerNext(index + 1);
                        }
                    });
                };

                registerNext(0);
            });
        }

        // Initial cart fetch
        $(document).ready(function() {
            fetchDropdownCart(false);
        });

        // Toggle cart panel
        $('#dropdown-cart-toggle').on('click', function() {
            $('#dropdown-cart-panel').toggle();
            fetchDropdownCart(true);
        });

        // Remove course from cart
        $(document).on('click', '.remove-from-cart-btn', function() {
            const cartId = $(this).data('cart-id');
            $.ajax({
                url: '/wp-json/course-api/v1/cart/remove',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    student_id: studentId,
                    cart_id: cartId
                }),
                success: function() {
                    fetchDropdownCart(true);
                    document.dispatchEvent(new Event('cart-updated'));
                }
            });
        });

        // Register for all courses from dropdown
        $('#register-dropdown-btn').on('click', registerForCoursesFromDropdown);

        // Hide dropdown when clicking outside
        $(document).on('click', function(e) {
            if (!$(e.target).closest('#dropdown-cart-container').length) {
                $('#dropdown-cart-panel').hide();
            }
        });

        // Custom event to refresh count externally
        document.addEventListener('cart-updated', function () {
            fetchDropdownCart(false);
        });

    })(jQuery);
    </script>
    <?php
    return ob_get_clean();
}



// === AJAX: Fetch Registered Courses ===
add_action('wp_ajax_get_registered_courses', 'handle_get_registered_courses');
function handle_get_registered_courses() {
    session_start();

    if (!isset($_SESSION['student_id'])) {
        wp_send_json(['success' => false, 'message' => 'Not logged in']);
    }

    $student_id = intval($_SESSION['student_id']);
    $response = wp_remote_get(home_url("/wp-json/course-api/v1/schedule/{$student_id}"));

    if (is_wp_error($response)) {
        wp_send_json(['success' => false, 'message' => 'Error contacting server']);
    }

    $body = wp_remote_retrieve_body($response);
    $registrations = json_decode($body, true);

    if (!is_array($registrations)) {
        wp_send_json(['success' => true, 'courses' => []]);
    }

    wp_send_json(['success' => true, 'courses' => $registrations]);
}

// === AJAX: Unregister from a Course ===
add_action('wp_ajax_unregister_course', 'handle_unregister_course');
function handle_unregister_course() {
    session_start();

    if (!isset($_SESSION['student_id'])) {
        wp_send_json(['success' => false, 'message' => 'Not logged in']);
    }

    $reg_id = intval($_POST['registration_id'] ?? 0);

    if (!$reg_id) {
        wp_send_json(['success' => false, 'message' => 'Invalid registration ID']);
    }

    $response = wp_remote_post(home_url('/wp-json/course-api/v1/unregister'), [
        'body'    => json_encode(['registration_id' => $reg_id]),
        'headers' => ['Content-Type' => 'application/json'],
    ]);

    if (is_wp_error($response)) {
        wp_send_json(['success' => false, 'message' => 'Server error']);
    }

    $body = wp_remote_retrieve_body($response);
    $result = json_decode($body, true);

    if (!empty($result['success'])) {
        wp_send_json(['success' => true, 'message' => 'Successfully unregistered']);
    } else {
        wp_send_json(['success' => false, 'message' => 'Failed to unregister']);
    }
}

add_shortcode('registered_courses', 'registered_courses_shortcode');
// === Shortcode: Display Registered Courses ===
function registered_courses_shortcode() {
    ob_start();
    ?>
    <div id="registered-courses">
        <h3 style="margin-bottom: 1rem; font-family: sans-serif;">Your Registered Courses</h3>
        <div id="courses-list">Loading...</div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            function fetchCourses() {
                $('#courses-list').html('Loading...');
                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    method: 'POST',
                    data: {
                        action: 'get_registered_courses'
                    },
                    success: function(response) {
                        if (response.success && response.courses.length > 0) {
                            let html = '';

                            response.courses.forEach(course => {
                                html += `
                                    <div class="course-card" id="course-${course.registration_id}">
                                        <div class="course-card-content">
                                            <span class="course-department course-code-box">${course.department} ${course.course_code}</span>
                                            <div style="margin-top: 10px" class="course-title">${course.course_name}</div>
                                            <div class="course-description">${course.description}</div>
                                        </div>
                                        <div style="margin-top: 10px;">`;

                                if (course.completed == 0) {
                                    html += `
                                            <button id="mark-comp" class="mark-complete-btn api-btn" data-reg-id="${course.registration_id}">Mark Completed</button>
                                            <button class="unregister-btn api-btn" data-reg-id="${course.registration_id}">Unregister</button>`;
                                } else {
                                    html += `<strong>Course Completed</strong>`;
                                }

                                html += `
                                        </div>
                                    </div>`;
                            });

                            $('#courses-list').html(html);
                        } else {
                            $('#courses-list').html('<p style="font-family: sans-serif;">No registered courses found.</p>');
                        }
                    },
                    error: function() {
                        $('#courses-list').html('<p style="font-family: sans-serif;">Failed to load courses.</p>');
                    }
                });
            }

            fetchCourses();

            $(document).on('click', '.unregister-btn', function() {
                const regId = $(this).data('reg-id');
                if (!confirm('Are you sure you want to unregister from this course?')) return;

                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    method: 'POST',
                    data: {
                        action: 'unregister_course',
                        registration_id: regId
                    },
                    success: function(response) {
                        alert(response.message);
                        if (response.success) {
                            fetchCourses();
                        }
                    },
                    error: function() {
                        alert('Error trying to unregister.');
                    }
                });
            });

            $(document).on('click', '.mark-complete-btn', function() {
                const regId = $(this).data('reg-id');
                const studentId = <?php echo get_current_user_id(); ?>;

                $.ajax({
                    url: '<?php echo esc_url(rest_url("course-api/v1/mark-completed")); ?>',
                    method: 'POST',
                    contentType: 'application/json',
                    data: JSON.stringify({
                        registration_id: regId,
                        student_id: studentId
                    }),
                    success: function(response) {
                        alert(response.message || 'Marked as completed!');
                        $(`#course-${regId}`).fadeOut();
                    },
                    error: function(xhr) {
                        let msg = 'Failed to mark course as completed.';
                        if (xhr.responseJSON && xhr.responseJSON.message) {
                            msg = xhr.responseJSON.message;
                        }
                        alert(msg);
                    }
                });
            });
        });
    </script>
    <?php
    return ob_get_clean();
}


add_shortcode('course_browser', 'render_course_browser_ui');
function render_course_browser_ui() {
    if (!isset($_SESSION['student_id'])) {
        return 'Please log in first.';
    }

    $student_id = intval($_SESSION['student_id']);

    ob_start(); ?>
    
    <div id="course-browser">
        <h2>Search Courses</h2>
        <input type="text" id="search-keyword" placeholder="Enter keyword (e.g. math, bio)">
        <input type="text" id="search-department" placeholder="Department (optional)">
        <button class="api-btn" id="search-btn">Search</button>

        <h3>Search Results</h3>
        <div id="search-results">Use the search above to find courses.</div>

        <h3>My Cart</h3>
        <div id="cart-results">Loading...</div>

        <button class="api-btn" id="register-btn" style="margin-top: 10px;">Register for All Courses</button>

        <div id="registration-confirmation" style="margin-top: 20px;"></div>
    </div>

    <script>
    (function($) {
        const studentId = <?php echo json_encode($student_id); ?>;

        function fetchCart() {
            $.get('/wp-json/course-api/v1/cart/' + studentId, function(data) {
                if (!data || data.length === 0) {
                    $('#cart-results').html('<p>Your cart is empty.</p>');
                    return;
                }
                
                const html = data.map(course => `
                <div class="course-card">
                    <div class="course-card-content">
                        <span class="course-department">${course.department || ''} ${course.course_code}</span>
                        <div class="course-title">${course.course_name}</div>
                        <div class="course-description">${course.description}</div>
                        <div class="course-description">Available Seats: ${course.capacity - course.seats_filled}</div>
                    </div>
                    <div>
                        <div class="course-credits">${course.credits} Credits</div>
                        <button class="remove-from-cart api-btn-inverse" data-cart-id="${course.cart_id}">Remove Course</button>
                    </div>
                </div>
                `).join('');

                $('#cart-results').html(html);
            });
        }

function searchCourses() {
    const keyword = $('#search-keyword').val();
    const department = $('#search-department').val();

    $.get('/wp-json/course-api/v1/courses/search', {
        keyword,
        department
    }, function(data) {
        if (!data || data.length === 0) {
            $('#search-results').html('<p>No matching courses found.</p>');
            return;
        }

        console.log('Courses found:', data);  // Debugging courses data

        // First, get completed courses for the current student
        $.get('/wp-json/course-api/v1/completed-courses/' + studentId, function(completedCourses) {

            // Map completed courses to a lookup object using course_id as key
            const completedCourseLookup = completedCourses.reduce((acc, course) => {
                const completedStatus = course.completed === "1";  // Convert "1" to true and "0" to false
                acc[course.course_id] = completedStatus;
                return acc;
            }, {});

            const courseHtmlPromises = data.map(course => {
                const available = course.capacity - course.seats_filled;
                const isFull = available <= 0;
                const notice = isFull ? '<span style="color: red;">(Full)</span>' : '';
                const waitlistButton = isFull
                    ? `<button class="add-to-waitlist api-btn" data-course-id="${course.course_id}">Add to Waitlist</button>`
                    : '';
                const addToCartButton = !isFull
                    ? `<button class="add-to-cart api-btn" data-course-id="${course.course_id}">Add to Cart</button>`
                    : '';

                console.log(`Processing course: ${course.course_name}, Available seats: ${available}`);  // Debugging each course's available seats

                // Fetch prerequisites for this course
                return $.get('/wp-json/course-api/v1/prerequisites/' + course.course_id).then(prereqs => {
                    console.log(`Prerequisites for course ${course.course_name}:`, prereqs);  // Debugging fetched prerequisites

                    let prereqHTML = '';
                    if (prereqs.length > 0) {
                        const formatted = prereqs.map(pr => {
                            const isCompleted = completedCourseLookup[pr.prerequisite_course_id] === true;  // Use completed field directly

                            const icon = isCompleted
                                ? '<span class="course-description" style="color:green;">✅</span>'  // Completed
                                : '<span class="course-description" style="color:red;">❌</span>';  // Not completed
                            return `<li>${icon} <span class="course-description"> ${pr.department} ${pr.course_code}: ${pr.course_name} </span> </li>`;
                        }).join('');  // Format the prerequisites list
                        prereqHTML = `<div><strong>Prerequisites:</strong><ul>${formatted}</ul></div>`;
                    }

                    return `
                    <div class="course-card">
                        <div class="course-card-content">
                            <span class="course-department">${course.department || ''} ${course.course_code}</span>
                            <div class="course-title">${course.course_name}</div>
                            <div class="course-description">${course.description}</div>
                            <div class="course-description">Available Seats: ${available} ${notice}</div>
                            ${prereqHTML}  <!-- Add prerequisites if available -->
                        </div>
                        <div>
                            <div class="course-credits">${course.credits} Credits</div>
                            ${addToCartButton}
                            ${waitlistButton}
                        </div>
                    </div>`;
                });
            });

            // Wait for all promises to resolve and then inject the full HTML
            Promise.all(courseHtmlPromises).then(allHtml => {
                console.log('All course HTML:', allHtml);  // Debugging final HTML for all courses
                $('#search-results').html(allHtml.join(''));
            });
        });
    });
}


        function registerForCourses() {
            $.get('/wp-json/course-api/v1/cart/' + studentId, function(courses) {
                if (!courses || courses.length === 0) {
                    alert('No courses to register for!');
                    return;
                }

                let successful = [];
                let failed = [];

                const registerNext = (index) => {
                    if (index >= courses.length) {
                        fetchCart();

                        const summary = `
                            <h4>Registration Summary</h4>
                            <p>✅ Registered: ${successful.length}</p>
                            <ul>${successful.map(c => `<li>${c}</li>`).join('')}</ul>
                            ${failed.length > 0 ? `<p>❌ Failed: ${failed.length}</p><ul>${failed.map(c => `<li>${c}</li>`).join('')}</ul>` : ''}
                        `;
                        $('#registration-confirmation').html(summary);
                        return;
                    }

                    const course = courses[index];
                    const available = (course.capacity || 0) - (course.seats_filled || 0);

                    if (available <= 0) {
                        failed.push(`${course.course_code}: ${course.course_name} (Full)`);
                        registerNext(index + 1);
                        return;
                    }

                    $.ajax({
                        url: '/wp-json/course-api/v1/register',
                        method: 'POST',
                        contentType: 'application/json',
                        data: JSON.stringify({
                            student_id: studentId,
                            course_id: course.course_id
                        }),
                        success: function() {
                            successful.push(`${course.course_code}: ${course.course_name}`);
                            registerNext(index + 1);
                        },
                        error: function(jqXHR) {
                            const msg = jqXHR.responseJSON?.message || 'Error';
                            failed.push(`${course.course_code}: ${course.course_name} (${msg})`);
                            registerNext(index + 1);
                        }
                    });
                };

                registerNext(0);
            });
        }

        // --- Event handlers ---
        $(document).on('click', '#search-btn', searchCourses);

        $(document).on('click', '.add-to-cart', function() {
            const courseId = $(this).data('course-id');
            $.post('/wp-json/course-api/v1/cart/add', {
                student_id: studentId,
                course_id: courseId
            }, function(response) {
                alert(response.message || 'Added!');
                document.dispatchEvent(new Event('cart-updated'));
                fetchCart();
            }).fail(err => {
                alert(err.responseJSON?.message || 'Failed to add.');
            });
        });

        $(document).on('click', '.remove-from-cart', function() {
            const cartId = $(this).data('cart-id');
            $.post('/wp-json/course-api/v1/cart/remove', {
                cart_id: cartId
            }, function(response) {
                alert(response.message || 'Removed!');
                document.dispatchEvent(new Event('cart-updated'));
                fetchCart();
            }).fail(err => {
                alert(err.responseJSON?.message || 'Failed to remove.');
            });
        });

        $(document).on('click', '#register-btn', registerForCourses);

        // ✅ Add-to-waitlist handler
        $(document).on('click', '.add-to-waitlist', function() {
            const courseId = $(this).data('course-id');

            $.ajax({
                url: '/wp-json/course-api/v1/waitlist/add',
                method: 'POST',
                contentType: 'application/json',
                data: JSON.stringify({
                    student_id: studentId,
                    course_id: courseId
                }),
                success: function(response) {
                    alert(response.message || 'You’ve been added to the waitlist.');
                    document.dispatchEvent(new Event('cart-updated'));
                },
                error: function(jqXHR) {
                    const msg = jqXHR.responseJSON?.message || 'Failed to join waitlist.';
                    alert(msg);
                }
            });
        });

        $(document).ready(function() {
            fetchCart();
        });
    })(jQuery);
    </script>

    <?php
    return ob_get_clean();
}

function add_to_cart_button($atts) {
    if (!isset($_SESSION['student_id'])) {
        return 'Please log in first.';
    }

    $course_id = isset($atts['course_id']) ? intval($atts['course_id']) : 0;
    if (!$course_id) {
        return 'Invalid course.';
    }

    ob_start();
    ?>
    <form method="POST" action="">
        <input type="hidden" name="course_id" value="<?php echo esc_attr($course_id); ?>">
        <button class="api-btn" type="submit">Add to Cart</button>
    </form>
    <?php

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['course_id']) && intval($_POST['course_id']) === $course_id) {
        $student_id = $_SESSION['student_id'];

        $response = wp_remote_post(home_url('/wp-json/course-api/v1/cart/add'), [
            'body' => json_encode(['student_id' => $student_id, 'course_id' => $course_id]),
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!empty($result['success'])) {
            echo '<p>Course added to cart successfully!</p>';
        } else {
            echo '<p>Failed to add course to cart.</p>';
        }
    }

    return ob_get_clean();
}
add_shortcode('add_to_cart_button', 'add_to_cart_button');

// Remove from Cart Button Shortcode
function remove_from_cart_button_shortcode($atts) {
    if (!isset($_SESSION['student_id'])) {
        return 'Please log in first.';
    }

    // Extract attributes (cart_id required)
    $atts = shortcode_atts(['cart_id' => ''], $atts);
    $cart_id = intval($atts['cart_id']);

    if (!$cart_id) {
        return 'Invalid cart ID.';
    }

    ob_start();
    ?>
    <form method="POST">
        <input type="hidden" name="cart_id" value="<?php echo esc_attr($cart_id); ?>">
        <button type="submit" name="remove_from_cart" class="api-btn-inverse">Remove from Cart</button>
    </form>
    <?php

    // Handle form submission
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['remove_from_cart']) && intval($_POST['cart_id']) === $cart_id) {
        $student_id = $_SESSION['student_id'];

        $response = wp_remote_post(home_url('/wp-json/course-api/v1/cart/remove'), [
            'body'    => json_encode(['student_id' => $student_id, 'cart_id' => $cart_id]),
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (!empty($result['success'])) {
            echo '<p>Course removed from cart successfully!</p>';
        } else {
            echo '<p>Failed to remove course from cart.</p>';
        }
    }

    return ob_get_clean();
}
add_shortcode('remove_from_cart', 'remove_from_cart_button_shortcode');

function display_login_form() {
    ob_start();

    if (!empty($_SESSION['student_id'])) {
        echo '<p>You are already logged in.</p>';
	echo do_shortcode('[logout_button]');
        return ob_get_clean();
    }

    ?>
    <form id="login-form" action="#" method="POST">
        <label for="email">Email:</label>
        <input type="email" id="email" name="email" required><br>

        <label for="password">Password:</label>
        <input type="password" id="password" name="password" required><br>

        <button type="submit" class="api-btn">Login</button>
    </form>
    <div id="login-response"></div>

    <?php
    if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['email']) && isset($_POST['password'])) {
        $email = sanitize_email($_POST['email']);
        $password = sanitize_text_field($_POST['password']);
        
        // Handle the login request to API
        $response = wp_remote_post(home_url('/wp-json/course-api/v1/login'), [
            'body' => json_encode(['email' => $email, 'password' => $password]),
            'headers' => ['Content-Type' => 'application/json'],
        ]);
        
        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);
        
        
        if (isset($result['success']) && $result['success']) {
            // Reset session first
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            session_unset();     // Clear any old data
            session_regenerate_id(true); // Prevent session fixation

            $_SESSION['student_id'] = $result['user']['student_id'];
            /* echo '<p>Login successful!</p>'; */
        }
        
        
        if (isset($result['success']) && $result['success']) {
            // Store user data in session
            $_SESSION['student_id'] = $result['user']['student_id'];
            echo '<p>Login successful!</p>';
	    wp_redirect($_SERVER['REQUEST_URI']);
        } else {
            echo '<p>Invalid credentials. Please try again.</p>';
        }
    }
    
    return ob_get_clean();
}
add_shortcode('login_form', 'display_login_form');


function display_courses() {
    ob_start();

    // Fetch courses from API
    $response = wp_remote_get(home_url('/wp-json/course-api/v1/courses'));
    $courses = json_decode(wp_remote_retrieve_body($response), true);

    if (!empty($courses)) {
        foreach ($courses as $index => $course) {
            echo '<div class="course-card">';
            echo '  <div class="course-info">';
            echo '      <div class="course-code-box">';
            echo '          <div>' . esc_html($course['department']) . '</div>';
            echo '          <div>' . esc_html($course['course_code']) . '</div>';
            echo '      </div>';
            echo '      <div class="course-meta">';
            echo '          <div class="title">' . esc_html($course['course_name']) . '</div>';
            echo '          <div class="desc">' . esc_html($course['description']) . '</div>';
            echo '      </div>';
            echo '  </div>';
            echo '  <div class="course-credits">' . esc_html($course['credits']) . ' Credits</div>';
            echo '</div>';
        }
    } else {
        echo 'No courses available.';
    }

    return ob_get_clean();
}
add_shortcode('view_courses', 'display_courses');

// View Cart Shortcode
function display_cart() {
    if (!isset($_SESSION['student_id'])) {
        return 'Please log in first.';
    }

    $student_id = $_SESSION['student_id'];
    $response = wp_remote_get(home_url("/wp-json/course-api/v1/cart/{$student_id}"));
    $cart = json_decode(wp_remote_retrieve_body($response), true);

    ob_start();

    if (!empty($cart)) {
        echo '<ul>';
        foreach ($cart as $course) {
            echo '<li>' . esc_html($course['course_name']) . ' - ' . esc_html($course['course_code']) . '</li>';
                $course_id = intval($course['course_id']); // Sanitize course ID
                $cart_id = intval($course['cart_id']); // Sanitize course ID
            echo '<li>' . do_shortcode('[remove_from_cart cart_id="' . $cart_id . '"]') . '</li>';
	}
        echo '</ul>';
    } else {
        echo 'Your cart is empty.';
    }

    return ob_get_clean();
}
add_shortcode('view_cart', 'display_cart');

// Add to Cart Shortcode
function add_course_to_cart() {
    if (!isset($_SESSION['student_id'])) {
        return 'Please log in first.';
    }

    if (isset($_POST['course_id'])) {
        $student_id = $_SESSION['student_id'];
        $course_id = sanitize_text_field($_POST['course_id']);

        $response = wp_remote_post(home_url('/wp-json/course-api/v1/cart/add'), [
            'body' => json_encode(['student_id' => $student_id, 'course_id' => $course_id]),
            'headers' => ['Content-Type' => 'application/json'],
        ]);

        $body = wp_remote_retrieve_body($response);
        $result = json_decode($body, true);

        if (isset($result['success']) && $result['success']) {
            return 'Course added to cart successfully!';
        } else {
            return 'Failed to add course to cart.';
        }
    }

    ob_start();
    ?>
    <form method="POST" action="#">
        <label for="course_id">Course ID:</label>
        <input type="text" id="course_id" name="course_id" required><br>
        <button type="submit" class="api-btn">Add to Cart</button>
    </form>
    <?php
    return ob_get_clean();
}
add_shortcode('add_to_cart', 'add_course_to_cart');

// Register for Course Shortcode
function register_courses_shortcode() {
    ob_start();
    ?>
    <div id="course-registration">
        <h3 style="margin-bottom: 1rem; font-family: sans-serif;">Available Courses</h3>
        <div id="course-list">Loading...</div>
    </div>

    <script>
        jQuery(document).ready(function($) {
            function fetchCourses() {
                $('#course-list').html('Loading...');
                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    method: 'POST',
                    data: {
                        action: 'get_available_courses'
                    },
                    success: function(response) {
                        if (response.success && response.courses.length > 0) {
                            let html = '';
                            response.courses.forEach(course => {
                                html += `
                                    <div class="course-card">
                                        <div class="course-card-content">
                                            <span class="course-department course-code-box">${course.department} ${course.course_code}</span>
                                            <div class="course-title" style="margin-top: 10px;">${course.course_name}</div>
                                            <div class="course-description">${course.description}</div>
                                            <div class="course-availability">Available Spots: <strong>${course.available_spots}</strong></div>
                                        </div>`;

                                // If the course is full, display "Add to Waitlist" button
                                if (course.available_spots <= 0) {
                                    html += `
                                        <button class="add-to-waitlist-btn api-btn" data-course-id="${course.course_id}">Add to Waitlist</button>
                                    `;
                                } else {
                                    html += `
                                        <button class="register-btn api-btn" data-course-id="${course.course_id}">Register</button>
                                    `;
                                }
                                
                                html += `</div>`;
                            });

                            $('#course-list').html(html);
                        } else {
                            $('#course-list').html('<p style="font-family: sans-serif;">No courses available at the moment.</p>');
                        }
                    },
                    error: function() {
                        $('#course-list').html('<p style="font-family: sans-serif;">Failed to load courses.</p>');
                    }
                });
            }

            fetchCourses();

            // Add to Waitlist button click event
            $(document).on('click', '.add-to-waitlist-btn', function() {
                const courseId = $(this).data('course-id');
                if (!confirm('Are you sure you want to add this course to your waitlist?')) return;

                $.ajax({
                    url: '<?php echo admin_url("admin-ajax.php"); ?>',
                    method: 'POST',
                    data: {
                        action: 'add_to_waitlist',
                        course_id: courseId
                    },
                    success: function(response) {
                        alert(response.message);
                    },
                    error: function() {
                        alert('Error trying to add to waitlist.');
                    }
                });
            });
        });
    </script>
    <?php
    return ob_get_clean();
}

add_action('wp_ajax_add_to_waitlist', 'handle_add_to_waitlist');
function handle_add_to_waitlist() {
    session_start();

    if (!isset($_SESSION['student_id'])) {
        wp_send_json(['success' => false, 'message' => 'Not logged in']);
    }

    $course_id = intval($_POST['course_id'] ?? 0);
    if (!$course_id) {
        wp_send_json(['success' => false, 'message' => 'Invalid course ID']);
    }

    // Assume you have a function that adds the student to the waitlist
    $result = add_student_to_waitlist($course_id, $_SESSION['student_id']);

    if ($result) {
        wp_send_json(['success' => true, 'message' => 'Added to waitlist']);
    } else {
        wp_send_json(['success' => false, 'message' => 'Failed to add to waitlist']);
    }
}



// Logout Button Shortcode
function display_logout_button() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['student_logout'])) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        session_unset();     // Clear all session variables
        session_destroy();   // Destroy the session
        setcookie(session_name(), '', time() - 3600, '/'); // Remove the cookie
	 wp_redirect($_SERVER['REQUEST_URI']);
        return '<p>You have been logged out.</p>';
    }

    if (!empty($_SESSION['student_id'])) {
        return '
            <form method="POST">
                <button type="submit" name="student_logout" class="api-btn">Logout</button>
            </form>
        ';
    }

    // If not logged in, show nothing
    return '';
}
add_shortcode('logout_button', 'display_logout_button');

?>
