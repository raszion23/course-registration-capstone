<?php
/**
 * Plugin Name: Course Registration API by Yanchuan
 * Description: Custom API endpoints for the Course Registration System
 * Version: 2.0 (Phase 2 implementation)
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

// Register our API endpoints when WordPress initializes
add_action('rest_api_init', function() {
    
    // Course related endpoints
    register_rest_route('course-api/v1', '/courses', [
        'methods' => 'GET',
        'callback' => 'get_all_courses',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('course-api/v1', '/courses/(?P<id>\d+)', [
        'methods' => 'GET',
        'callback' => 'get_single_course',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('course-api/v1', '/courses/search', [
        'methods' => 'GET',
        'callback' => 'search_courses',
        'permission_callback' => '__return_true'
    ]);
    
    // Shopping cart endpoints
    register_rest_route('course-api/v1', '/cart/add', [
        'methods' => 'POST',
        'callback' => 'add_to_cart',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('course-api/v1', '/cart/remove', [
        'methods' => 'POST',
        'callback' => 'remove_from_cart',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('course-api/v1', '/cart/(?P<student_id>\d+)', [
        'methods' => 'GET',
        'callback' => 'get_cart',
        'permission_callback' => '__return_true'
    ]);
    
    // Registration endpoints
    register_rest_route('course-api/v1', '/register', [
        'methods' => 'POST',
        'callback' => 'register_for_course',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('course-api/v1', '/unregister', [
        'methods' => 'POST',
        'callback' => 'unregister_course',
        'permission_callback' => '__return_true'
    ]);
    
    // Schedule endpoints
    register_rest_route('course-api/v1', '/schedule/(?P<student_id>\d+)', [
        'methods' => 'GET',
        'callback' => 'get_student_schedule',
        'permission_callback' => '__return_true'
    ]);
    
    // User authentication endpoints
    register_rest_route('course-api/v1', '/login', [
        'methods' => 'POST',
        'callback' => 'user_login',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('course-api/v1', '/register-user', [
        'methods' => 'POST',
        'callback' => 'user_register',
        'permission_callback' => '__return_true'
    ]);
    
    // ================ NEW ENDPOINTS FOR PHASE 2 ================
    
    // Waitlist endpoints
    register_rest_route('course-api/v1', '/waitlist/add', [
        'methods' => 'POST',
        'callback' => 'add_to_waitlist',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('course-api/v1', '/waitlist/(?P<student_id>\d+)', [
        'methods' => 'GET',
        'callback' => 'get_student_waitlist',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('course-api/v1', '/waitlist/remove', [
        'methods' => 'POST',
        'callback' => 'remove_from_waitlist',
        'permission_callback' => '__return_true'
    ]);
    
    // Prerequisites endpoints
    register_rest_route('course-api/v1', '/prerequisites/(?P<course_id>\d+)', [
        'methods' => 'GET',
        'callback' => 'get_course_prerequisites',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('course-api/v1', '/completed-courses/(?P<student_id>\d+)', [
        'methods' => 'GET',
        'callback' => 'get_completed_courses',
        'permission_callback' => '__return_true'
    ]);
    
    register_rest_route('course-api/v1', '/mark-completed', [
        'methods' => 'POST',
        'callback' => 'mark_course_completed',
        'permission_callback' => '__return_true'
    ]);
});

// Course related functions
function get_all_courses() {
    global $wpdb;
    
    // Get courses from our custom table
    $courses = $wpdb->get_results(
        "SELECT * FROM `cr_course` WHERE 1=1",
        ARRAY_A
    );
    
    // Return the results
    return rest_ensure_response($courses);
}

function get_single_course($request) {
    global $wpdb;
    
    // Get the course ID from the URL
    $id = $request['id'];
    
    // Get the course from our custom table
    $course = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM `cr_course` WHERE course_id = %d",
            $id
        ),
        ARRAY_A
    );
    
    // Return the result
    return rest_ensure_response($course);
}


function search_courses($request) {
    global $wpdb;

    $keyword = isset($request['keyword']) ? sanitize_text_field($request['keyword']) : '';
    $department = isset($request['department']) ? sanitize_text_field($request['department']) : '';

    $where_clauses = [];
    $prepare_values = [];

    if (!empty($keyword)) {
        $where_clauses[] = "(course_name LIKE %s OR course_code LIKE %s OR description LIKE %s)";
        $keyword_param = '%' . $wpdb->esc_like($keyword) . '%';
        $prepare_values[] = $keyword_param;
        $prepare_values[] = $keyword_param;
        $prepare_values[] = $keyword_param;
    }

    if (!empty($department)) {
        $where_clauses[] = "department = %s";
        $prepare_values[] = $department;
    }

    $where_sql = '';
    if (!empty($where_clauses)) {
        $where_sql = 'WHERE ' . implode(' AND ', $where_clauses);
    }

    $sql = "SELECT * FROM `cr_course` $where_sql";

    if (!empty($prepare_values)) {
        $sql = $wpdb->prepare($sql, ...$prepare_values);
    }

    $results = $wpdb->get_results($sql, ARRAY_A);

    return rest_ensure_response($results);
}


// Shopping cart functions
function add_to_cart($request) {
    global $wpdb;
    
    // Get data from the request
    $student_id = $request['student_id'];
    $course_id = $request['course_id'];
    
    // Check if already in cart
    $existing = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM cr_shopping_cart 
            WHERE student_id = %d AND course_id = %d",
            $student_id,
            $course_id
        )
    );
    
    if ($existing > 0) {
        return new WP_Error(
            'already_in_cart',
            'Course is already in shopping cart',
            ['status' => 400]
        );
    }
    
    // Add to cart
    $result = $wpdb->insert(
        "cr_shopping_cart",
        [
            'student_id' => $student_id,
            'course_id' => $course_id,
            'added_date' => current_time('mysql')
        ]
    );
    
    // Check if successful
    if ($result) {
        return [
            'success' => true,
            'message' => 'Course added to cart'
        ];
    } else {
        return new WP_Error(
            'cart_add_failed',
            'Failed to add course to cart',
            ['status' => 500]
        );
    }
}

function remove_from_cart($request) {
    global $wpdb;
    
    // Get cart ID from the request
    $cart_id = $request['cart_id'];
    
    // Remove from cart
    $result = $wpdb->delete(
        "cr_shopping_cart",
        ['cart_id' => $cart_id]
    );
    
    // Check if successful
    if ($result) {
        return [
            'success' => true,
            'message' => 'Course removed from cart'
        ];
    } else {
        return new WP_Error(
            'cart_remove_failed',
            'Failed to remove course from cart',
            ['status' => 500]
        );
    }
}

function get_cart($request) {
    global $wpdb;
    
    // Get student ID from the URL
    $student_id = $request['student_id'];
    
    // Get cart items with course details
    $cart_items = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT c.*, sc.cart_id, sc.added_date 
            FROM cr_shopping_cart sc
            JOIN cr_course c ON sc.course_id = c.course_id
            WHERE sc.student_id = %d",
            $student_id
        ),
        ARRAY_A
    );
    
    // Return the results
    return rest_ensure_response($cart_items);
}

// Registration function
function register_for_course($request) {
    global $wpdb;
    
    // Get data from the request
    $student_id = $request['student_id'];
    $course_id = $request['course_id'];
    
    // Check if the student is already registered
    $existing = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM cr_registration 
             WHERE student_id = %d AND course_id = %d AND status = 'confirmed'",
            $student_id,
            $course_id
        )
    );
    
    if ($existing > 0) {
        return new WP_Error(
            'already_registered',
            'Student is already registered for this course',
            ['status' => 400]
        );
    }
    
    // NEW: Check prerequisites
    $prerequisites = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.prerequisite_course_id, c.course_name, c.course_code 
             FROM cr_prerequisites p
             JOIN cr_course c ON p.prerequisite_course_id = c.course_id
             WHERE p.course_id = %d",
            $course_id
        ),
        ARRAY_A
    );
    
    $missing_prerequisites = [];
    
    foreach ($prerequisites as $prerequisite) {
        $completed = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT COUNT(*) FROM cr_registration 
                 WHERE student_id = %d 
                 AND course_id = %d 
                 AND completed = TRUE",
                $student_id,
                $prerequisite['prerequisite_course_id']
            )
        );
        
        if ($completed == 0) {
            $missing_prerequisites[] = [
                'id' => $prerequisite['prerequisite_course_id'],
                'name' => $prerequisite['course_name'],
                'code' => $prerequisite['course_code']
            ];
        }
    }
    
    if (!empty($missing_prerequisites)) {
        return new WP_Error(
            'prerequisites_not_met',
            'You have not completed all prerequisite courses',
            ['status' => 400, 'missing_prerequisites' => $missing_prerequisites]
        );
    }
    
    // Check course capacity
    $course = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT capacity, seats_filled FROM cr_course WHERE course_id = %d",
            $course_id
        )
    );
    
    if ($course->seats_filled >= $course->capacity) {
        return new WP_Error(
            'course_full',
            'Course has reached maximum capacity',
            ['status' => 400]
        );
    }
    
    // Start transaction to ensure data consistency
    $wpdb->query('START TRANSACTION');
    
    // Add the registration
    $registration_result = $wpdb->insert(
        "cr_registration",
        [
            'student_id' => $student_id,
            'course_id' => $course_id,
            'registration_date' => current_time('mysql'),
            'status' => 'confirmed',
            'completed' => false
        ]
    );
    
    // Update course seats filled
    $update_result = $wpdb->update(
        "cr_course",
        ['seats_filled' => $course->seats_filled + 1],
        ['course_id' => $course_id]
    );
    
    // Remove from shopping cart
    $wpdb->delete(
        "cr_shopping_cart",
        [
            'student_id' => $student_id,
            'course_id' => $course_id
        ]
    );
    
    // Commit or rollback based on results
    if ($registration_result && $update_result) {
        $wpdb->query('COMMIT');
        return [
            'success' => true,
            'message' => 'Registration successful'
        ];
    } else {
        $wpdb->query('ROLLBACK');
        return new WP_Error(
            'registration_failed',
            'Failed to register for the course',
            ['status' => 500]
        );
    }
}

// Schedule function
function get_student_schedule($request) {
    global $wpdb;
    
    // Get student ID from the URL
    $student_id = $request['student_id'];
    
    // Get student's schedule with course and instructor details
    $schedule = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT c.*, r.registration_id, r.registration_date, r.status, r.completed,
            i.first_name as instructor_first_name, i.last_name as instructor_last_name
            FROM cr_registration r
            JOIN cr_course c ON r.course_id = c.course_id
            JOIN cr_instructors i ON c.instructor_id = i.instructor_id
            WHERE r.student_id = %d AND r.status = 'confirmed'",
            $student_id
        ),
        ARRAY_A
    );
    
    // Return the results
    return rest_ensure_response($schedule);
}

// User authentication functions
function user_login($request) {
    global $wpdb;
    
    // Get login credentials
    $email = $request['email'];
    $password = $request['password'];
    
    // Find student by email
    $student = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM cr_student WHERE email = %s",
            $email
        ),
        ARRAY_A
    );
    
    // Check if student exists
    if (!$student) {
        return new WP_Error(
            'invalid_credentials',
            'Email not found',
            ['status' => 401]
        );
    }
     
    // Verify password (in real app, use password_verify)
    // This assumes passwords are not hashed in the database
    if ($password !== $student['password']) {
        return new WP_Error(
            'invalid_credentials',
            'Incorrect password',
            ['status' => 401]
        );
    }
    
    // Remove password from response for security
    unset($student['password']);
    
    // Return user data
    return [
        'success' => true,
        'user' => $student
    ];
}

function user_register($request) {
    global $wpdb;

    // Get parameters properly from REST request
    $first_name = $request->get_param('first_name');
    $last_name = $request->get_param('last_name');
    $email = $request->get_param('email');
    $password = $request->get_param('password');
    $phone_number = $request->get_param('phone_number') ?? '';
    $major = $request->get_param('major') ?? '';

    if (!$first_name || !$last_name || !$email || !$password) {
        return new WP_Error('missing_fields', 'Required fields are missing.', ['status' => 400]);
    }

    // Check if email already exists
    $existing = $wpdb->get_var(
        $wpdb->prepare("SELECT COUNT(*) FROM cr_student WHERE email = %s", $email)
    );

    if ($existing > 0) {
        return new WP_Error('email_exists', 'Email already registered', ['status' => 400]);
    }

    // Hash password
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $hashed_password = $password;

    // Insert into DB
    $result = $wpdb->insert("cr_student", [
        'first_name'   => $first_name,
        'last_name'    => $last_name,
        'email'        => $email,
        'password'     => $hashed_password,
        'phone_number' => $phone_number,
        'major'        => $major
    ]);

    if ($result) {
        $student_id = $wpdb->insert_id;
        return [
            'success' => true,
            'message' => 'Registration successful',
            'user_id' => $student_id
        ];
    } else {
        return new WP_Error(
            'registration_failed',
            'Failed to register user: ' . $wpdb->last_error,
            ['status' => 500]
        );
    }
}

function unregister_course($request) {
    global $wpdb;

    $registration_id = intval($request['registration_id']);

    if (!$registration_id) {
        return new WP_Error('invalid_id', 'Invalid registration ID', ['status' => 400]);
    }

    $registration = $wpdb->get_row(
        $wpdb->prepare("SELECT * FROM cr_registration WHERE registration_id = %d", $registration_id),
        ARRAY_A
    );

    if (!$registration) {
        return new WP_Error('not_found', 'Registration not found', ['status' => 404]);
    }

    $course_id = $registration['course_id'];
    $student_id = $registration['student_id'];

    $wpdb->query('START TRANSACTION');

    // Delete registration
    $deleted = $wpdb->delete(
        "cr_registration",
        ['registration_id' => $registration_id]
    );

    // Update course seats filled
    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE cr_course SET seats_filled = seats_filled - 1 WHERE course_id = %d AND seats_filled > 0",
            $course_id
        )
    );

    // NEW: Check for waitlisted students and promote the first one
    $next_student = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM cr_waitlist 
             WHERE course_id = %d 
             ORDER BY waitlist_position ASC 
             LIMIT 1",
            $course_id
        )
    );

    $promoted = false;
    $promoted_student_id = null;
    
    if ($next_student) {
        // Register the waitlisted student
        $registration_result = $wpdb->insert(
            "cr_registration",
            [
                'student_id' => $next_student->student_id,
                'course_id' => $course_id,
                'registration_date' => current_time('mysql'),
                'status' => 'confirmed',
                'completed' => false
            ]
        );
        
        // Update course seats filled
        $update_result = $wpdb->query(
            $wpdb->prepare(
                "UPDATE cr_course SET seats_filled = seats_filled + 1 WHERE course_id = %d",
                $course_id
            )
        );
        
        // Remove from waitlist
        $waitlist_result = $wpdb->delete(
            "cr_waitlist",
            ['waitlist_id' => $next_student->waitlist_id]
        );
        
        if ($registration_result && $update_result && $waitlist_result) {
            $promoted = true;
            $promoted_student_id = $next_student->student_id;
        }
    }

    if ($deleted && $updated !== false) {
        $wpdb->query('COMMIT');
        
        $response = [
            'success' => true,
            'message' => 'Unregistered successfully'
        ];
        
        if ($promoted) {
            $response['promoted'] = true;
            $response['promoted_student_id'] = $promoted_student_id;
        }
        
        return $response;
    } else {
        $wpdb->query('ROLLBACK');
        return new WP_Error('unregister_failed', 'Failed to unregister from course', ['status' => 500]);
    }
}

// ================ NEW FUNCTIONS FOR PHASE 2 ================

/**
 * Add a student to the waitlist for a course
 */
function add_to_waitlist($request) {
    global $wpdb;
    
    $student_id = $request['student_id'];
    $course_id = $request['course_id'];
    
    if (!$student_id || !$course_id) {
        return new WP_Error(
            'missing_parameters',
            'Required parameters are missing',
            ['status' => 400]
        );
    }
    
    // Check if already on waitlist
    $existing = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM cr_waitlist 
             WHERE student_id = %d AND course_id = %d",
            $student_id,
            $course_id
        )
    );
    
    if ($existing > 0) {
        return new WP_Error(
            'already_waitlisted',
            'You are already on the waitlist for this course',
            ['status' => 400]
        );
    }
    
    // Check if already registered
    $registered = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT COUNT(*) FROM cr_registration 
             WHERE student_id = %d AND course_id = %d AND status = 'confirmed'",
            $student_id,
            $course_id
        )
    );
    
    if ($registered > 0) {
        return new WP_Error(
            'already_registered',
            'You are already registered for this course',
            ['status' => 400]
        );
    }
    
    // Get current highest waitlist position for this course
    $max_position = $wpdb->get_var(
        $wpdb->prepare(
            "SELECT MAX(waitlist_position) FROM cr_waitlist WHERE course_id = %d",
            $course_id
        )
    );
    
    $position = $max_position ? intval($max_position) + 1 : 1;
    
    // Add to waitlist
    $result = $wpdb->insert(
        "cr_waitlist",
        [
            'student_id' => $student_id,
            'course_id' => $course_id,
            'waitlist_date' => current_time('mysql'),
            'waitlist_position' => $position
        ]
    );
    
    if ($result) {
        $waitlist_id = $wpdb->insert_id;
        return [
            'success' => true,
            'message' => 'Successfully added to waitlist',
            'waitlist_id' => $waitlist_id,
            'position' => $position
        ];
    } else {
        return new WP_Error(
            'waitlist_failed',
            'Could not add to waitlist',
            ['status' => 500]
        );
    }
}

/**
 * Get a student's waitlisted courses
 */
function get_student_waitlist($request) {
    global $wpdb;
    
    $student_id = $request['student_id'];
    
    // Get waitlisted courses with details
    $waitlist = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT w.waitlist_id, w.waitlist_position, w.waitlist_date, 
                    c.course_id, c.department, c.course_code, c.course_name, 
                    c.description, c.credits, c.capacity, c.seats_filled
             FROM cr_waitlist w
             JOIN cr_course c ON w.course_id = c.course_id
             WHERE w.student_id = %d
             ORDER BY w.waitlist_position ASC",
            $student_id
        ),
        ARRAY_A
    );
    
    return rest_ensure_response($waitlist);
}

/**
 * Remove a student from a waitlist
 */
function remove_from_waitlist($request) {
    global $wpdb;
    
    $waitlist_id = $request['waitlist_id'];
    
    if (!$waitlist_id) {
        return new WP_Error(
            'missing_parameter',
            'Waitlist ID is required',
            ['status' => 400]
        );
    }
    
    // Get the waitlist entry details before deletion
    $waitlist_entry = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT * FROM cr_waitlist WHERE waitlist_id = %d",
            $waitlist_id
        )
    );
    
    if (!$waitlist_entry) {
        return new WP_Error(
            'not_found',
            'Waitlist entry not found',
            ['status' => 404]
        );
    }
    
    $course_id = $waitlist_entry->course_id;
    $position = $waitlist_entry->waitlist_position;
    
    $wpdb->query('START TRANSACTION');
    
    // Delete the waitlist entry
    $deleted = $wpdb->delete(
        "cr_waitlist",
        ['waitlist_id' => $waitlist_id]
    );
    
    // Update positions for other waitlisted students
    $updated = $wpdb->query(
        $wpdb->prepare(
            "UPDATE cr_waitlist 
             SET waitlist_position = waitlist_position - 1 
             WHERE course_id = %d AND waitlist_position > %d",
            $course_id,
            $position
        )
    );
    
    if ($deleted) {
        $wpdb->query('COMMIT');
        return [
            'success' => true,
            'message' => 'Removed from waitlist'
        ];
    } else {
        $wpdb->query('ROLLBACK');
        return new WP_Error(
            'removal_failed',
            'Could not remove from waitlist',
            ['status' => 500]
        );
    }
}

/**
 * Get prerequisites for a course
 */
function get_course_prerequisites($request) {
    global $wpdb;
    
    $course_id = $request['course_id'];
    
    // Get course prerequisites with details
    $prerequisites = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT p.prerequisite_id, p.prerequisite_course_id,
                    c.course_name, c.course_code, c.department, c.description
             FROM cr_prerequisites p
             JOIN cr_course c ON p.prerequisite_course_id = c.course_id
             WHERE p.course_id = %d",
            $course_id
        ),
        ARRAY_A
    );
    
    return rest_ensure_response($prerequisites);
}

/**
 * Get a student's completed courses
 */
function get_completed_courses($request) {
    global $wpdb;
    
    $student_id = $request['student_id'];
    
    // Get completed courses with details
    $completed_courses = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT r.registration_id, r.registration_date, r.completed,
                    c.course_id, c.department, c.course_code, c.course_name, 
                    c.description, c.credits
             FROM cr_registration r
             JOIN cr_course c ON r.course_id = c.course_id
             WHERE r.student_id = %d AND r.completed = TRUE
             ORDER BY r.registration_date DESC",
            $student_id
        ),
        ARRAY_A
    );
    
    return rest_ensure_response($completed_courses);
}

/**
 * Mark a course as completed for a student
 * This function allows marking a course as completed even if not registered
 * by auto-registering the student for the course and marking it completed
 */
function mark_course_completed($request) {
    global $wpdb;
    
    // Get parameters from the request
    $registration_id = isset($request['registration_id']) ? intval($request['registration_id']) : 0;
    $student_id = isset($request['student_id']) ? intval($request['student_id']) : 0;
    $course_id = isset($request['course_id']) ? intval($request['course_id']) : 0;
    
    // Check if we have either registration_id OR both student_id and course_id
    if (!$registration_id && (!$student_id || !$course_id)) {
        return new WP_Error(
            'missing_parameters',
            'Either registration_id or both student_id and course_id are required',
            ['status' => 400]
        );
    }
    
    // If registration_id is provided
    if ($registration_id) {
        // Check if registration exists
        $registration = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM cr_registration WHERE registration_id = %d",
                $registration_id
            )
        );
        
        if (!$registration) {
            return new WP_Error(
                'not_found',
                'Registration not found',
                ['status' => 404]
            );
        }
        
        // Update registration to completed

            $result = $wpdb->update(
                "cr_registration",
                ['completed' => true],
                [
                    'student_id' => $student_id,
                    'course_id' => $course_id,
                    'status' => 'confirmed'
                ]
            );
        
        if ($result !== false) {
            return [
                'success' => true,
                'message' => 'Course marked as completed',
                'auto_registered' => false
            ];
        } else {
            return new WP_Error(
                'update_failed',
                'Failed to mark course as completed',
                ['status' => 500]
            );
        }
    }
    // If student_id and course_id are provided
    else {
        // Check if the student is already registered
        $existing_reg = $wpdb->get_row(
            $wpdb->prepare(
                "SELECT * FROM cr_registration 
                 WHERE student_id = %d AND course_id = %d AND status = 'confirmed'",
                $student_id,
                $course_id
            )
        );
        
        // If registration exists, update it
        if ($existing_reg) {
            $updated = $wpdb->update(
                "cr_registration",
                ['completed' => true],
                [
                    'student_id' => $student_id,
                    'course_id' => $course_id,
                    'status' => 'completed'
                ]
            );
            
            if ($updated !== false) {
                return [
                    'success' => true,
                    'message' => 'Course marked as completed',
                    'auto_registered' => false
                ];
            } else {
                return new WP_Error(
                    'update_failed',
                    'Failed to mark course as completed',
                    ['status' => 500]
                );
            }
        }
        // If no registration exists, create one
        else {
            // Start transaction
            $wpdb->query('START TRANSACTION');
            
            // Insert registration
            $registration_result = $wpdb->insert(
                "cr_registration",
                [
                    'student_id' => $student_id,
                    'course_id' => $course_id,
                    'registration_date' => current_time('mysql'),
                    'status' => 'completed',
                    'completed' => true
                ]
            );
            
            // Commit or rollback based on results
            if ($registration_result) {
                $wpdb->query('COMMIT');
                return [
                    'success' => true,
                    'message' => 'Course registered and marked as completed',
                    'auto_registered' => true
                ];
            } else {
                $wpdb->query('ROLLBACK');
                return new WP_Error(
                    'registration_failed',
                    'Failed to register and mark course as completed',
                    ['status' => 500]
                );
            }
        }
    }
}
?>
