<?php
/*
Plugin Name: Quiz Display post
Plugin URI:
Description: Plugin hiển thị câu hỏi trắc nghiệm với tính năng tính điểm và xáo trộn
Version: 1.6
Author: Thay Do
*/

if (!defined('ABSPATH')) {
    exit;
}

// Kích hoạt plugin
register_activation_hook(__FILE__, 'quiz_display_activate');

function quiz_display_activate() {
    global $wpdb;

     // Tạo bảng quiz_results
    $table_name = $wpdb->prefix . 'quiz_results';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        user_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        quiz_id VARCHAR(255) NOT NULL,
        score_part_1 DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        score_part_2 DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        score_part_3 DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        total_score DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        start_time DATETIME NOT NULL,
        end_time DATETIME NOT NULL,
        student_name VARCHAR(255) NOT NULL,
        post_id BIGINT(20) UNSIGNED NOT NULL DEFAULT 0,
        PRIMARY KEY (id)
    ) $charset_collate;";

    // Tạo bảng quiz_answers riêng biệt
    $answers_table = $wpdb->prefix . 'quiz_answers';
    $sql_answers = "CREATE TABLE IF NOT EXISTS $answers_table (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        result_id BIGINT(20) UNSIGNED NOT NULL,
        question_id VARCHAR(255) NOT NULL,
        question_type VARCHAR(50) NOT NULL,
        student_answer TEXT NOT NULL,
        is_correct TINYINT(1) NOT NULL DEFAULT 0,
        PRIMARY KEY (id),
        FOREIGN KEY (result_id) REFERENCES $table_name(id) ON DELETE CASCADE
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    dbDelta($sql_answers);
}

// Enqueue scripts
function quiz_display_enqueue_scripts() {
    wp_enqueue_style('quiz-display-style', plugins_url('css/quiz-style.css', __FILE__));
    wp_enqueue_script('mathjs', 'https://math.booktoan.com/math.js', array(), null, true);
    wp_enqueue_script('quiz-display-script', plugins_url('js/quiz-script.js', __FILE__), array('jquery', 'mathjs'), '1.6', true);
    
    
    $settings = get_option('quiz_display_settings');
    wp_localize_script('quiz-display-script', 'quizDisplaySettings', $settings);
    wp_localize_script('quiz-display-script', 'quizData', array(
        'postId' => get_the_ID(),
        'ajaxurl' => admin_url('admin-ajax.php')
    ));
}

add_action('wp_enqueue_scripts', 'quiz_display_enqueue_scripts');

// Hàm mở rộng các thẻ HTML được phép
function quiz_display_allowed_html() {
    $allowed_tags = wp_kses_allowed_html('post');
    $allowed_tags['img'] = array(
        'src' => true,
        'alt' => true,
        'title' => true,
        'class' => true,
        'style' => true,
        'width' => true,
        'height' => true,
    );
    return $allowed_tags;
}

// Hàm xử lý biến số
function replace_variables($text, &$variables) {
    return preg_replace_callback(
        '/\!([a-zA-Z0-9*]+)(?::(-?\d+):(-?\d+))?\!/',
        function($matches) use (&$variables) {
            $varName = $matches[1];
            $min = isset($matches[2]) ? (int)$matches[2] : -10;
            $max = isset($matches[3]) ? (int)$matches[3] : 10;

            $isNonZero = strpos($varName, '*0') !== false;
            $varName = str_replace('*0', '', $varName);

            if (!array_key_exists($varName, $variables)) {
                do {
                    $value = rand($min, $max);
                } while ($isNonZero && $value === 0);
                $variables[$varName] = $value;
            }

            return $variables[$varName];
        },
        $text
    );
}
function process_variables_php($text, &$variables) {
    if (!is_string($text)) return $text;
    $pattern = '/(.*?)(<[^>]+>|$)/s';
    $output = '';
    
    preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
    foreach ($matches as $match) {
        $text_part = $match[1];
        $html_part = $match[2];
        if (!empty($text_part)) {
            $text_part = preg_replace_callback(
                '/\!([a-zA-Z0-9]+(?:\*0)?)(?::(-?\d+):(-?\d+))?\!/',
                function($matches) use (&$variables) {
                    $varName = $matches[1];
                    $min = isset($matches[2]) ? (int)$matches[2] : -10;
                    $max = isset($matches[3]) ? (int)$matches[3] : 10;
                    $isNonZero = strpos($varName, '*0') !== false;
                    $varName = str_replace('*0', '', $varName);
                    
                    if (!array_key_exists($varName, $variables)) {
                        do {
                            $value = rand($min, $max);
                        } while ($isNonZero && $value === 0);
                        $variables[$varName] = $value;
                    } else {
                        $value = $variables[$varName];
                    }
                    
                    return $value;
                },
                $text_part
            );
            if (substr($text_part, 0, 1) === '+') {
                $text_part = substr($text_part, 1);
            }
            
            $text_part = preg_replace_callback(
                '/([+-]?)(\d*\.?\d+)([x](?:\^?\d*)?(?:_[0-9]+)?)?/',
                function ($matches) {
                    $sign = $matches[1] ?: '';
                    $originalNumber = $matches[2]; // Giữ nguyên chuỗi số gốc
                    $coefficient = (float)$originalNumber;
                    $xPart = $matches[3] ?? '';
                    
                    if ($coefficient == 0 && !empty($xPart)) {
                        return '';
                    }
                    
                    if ($coefficient < 0) {
                        $sign = ($sign == '+') ? '-' : '+';
                        $coefficient = abs($coefficient);
                        $originalNumber = abs((float)$originalNumber);
                    }
                    
                    if ($xPart) {
                        if ($coefficient == 1) {
                            return $sign . $xPart;
                        } else {
                            // Sử dụng định dạng số gốc để giữ nguyên các số 0 ở đầu số thập phân
                            return $sign . $originalNumber . $xPart;
                        }
                    } else {
                        return $sign . $originalNumber;
                    }
                },
                $text_part
            );
            
            $text_part = str_replace('+-', '-', $text_part);
            
            if ($text_part == "=0") {
                $text_part = "0=0";
            } elseif (empty($text_part)) {
                $text_part = "0";
            }
        }
        $output .= $text_part . $html_part;
    }
    return $output;
}
// Shortcode cho bộ câu hỏi
function quiz_set_shortcode($atts, $content = null) {
    $atts = shortcode_atts(array(
        'type' => 'practice',
        'time' => 5,
        'single_choice_points' => 0.25,
        'true_false_points' => 0.25,
        'short_answer_points' => 0.5,
        'single_choice_tron' => 'n,n',
        'true_false_tron' => 'n,n',   
        'short_answer_tron' => 'n',
        'single_choice_socau' => 0,
        'true_false_socau' => 0,
        'short_answer_socau' => 0
    ), $atts);

    $quiz_id = 'quiz_set_' . uniqid();
    $variables = array();
    $content = do_shortcode(shortcode_unautop($content));

    $quiz_class = ($atts['type'] === 'exam') ? 'quiz-set-exam' : 'quiz-set-practice';
    $post_title = get_the_title(get_the_ID());

    $output = '<div class="quiz-set ' . $quiz_class . '" id="' . $quiz_id . '" 
                data-type="' . esc_attr($atts['type']) . '" 
                data-time="' . intval($atts['time']) . '" 
                data-post-title="' . esc_attr($post_title) . '"
                data-single-choice-points="' . floatval($atts['single_choice_points']) . '"
                data-true-false-points="' . floatval($atts['true_false_points']) . '"
                data-short-answer-points="' . floatval($atts['short_answer_points']) . '"
                data-single-choice-tron="' . esc_attr($atts['single_choice_tron']) . '"
                data-true-false-tron="' . esc_attr($atts['true_false_tron']) . '"
                data-short-answer-tron="' . esc_attr($atts['short_answer_tron']) . '"
                data-single-choice-socau="' . intval($atts['single_choice_socau']) . '"
                data-true-false-socau="' . intval($atts['true_false_socau']) . '"
                data-short-answer-socau="' . intval($atts['short_answer_socau']) . '">';

    if ($atts['type'] === 'exam') {
        $output .= '<div class="quiz-header">
                        <div class="quiz-student-info">
                            <label for="student_name_' . $quiz_id . '">Họ và tên:</label>
                            <input type="text" name="student_name" id="student_name_' . $quiz_id . '" class="student-name-input">
                        </div>
                        <div class="quiz-timer" style="display:none;">Thời gian còn lại: <span id="timer_' . $quiz_id . '"></span></div>
                        <button class="start-quiz">Làm bài</button>
                    </div>';
    }

    $output .= '<div class="quiz-questions" style="' . ($atts['type'] === 'exam' ? 'display:none;' : '') . '">' . $content . '</div>';

    $output .= '<div class="quiz-score" style="display:none;">
                    <h3>Kết quả</h3>
                    <p class="student-name-display" style="color: blue; font-weight: bold;"></p>
                    <p>Điểm phần I: <span class="score-part-1">0</span></p>
                    <p>Điểm phần II: <span class="score-part-2">0</span></p>
                    <p>Điểm phần III: <span class="score-part-3">0</span></p>
                    <p><strong><span style="color: red;">Tổng điểm bài thi:</span> <span class="total-score" style="color: red;">0</span></strong></p>
                    <p class="quiz-result-message"></p>
                </div>
                <button class="submit-quiz" style="' . ($atts['type'] === 'exam' ? 'display:none;' : '') . '">Nộp bài</button>
                <button class="retry-quiz" style="display:none;">Làm lại</button>
            </div>';

    return $output;
}
add_shortcode('quiz_set', 'quiz_set_shortcode');

// Shortcode cho câu hỏi trắc nghiệm một đáp án
function quiz_question_shortcode($atts, $content = null, $tag = '', &$variables = array()) {
    $atts = shortcode_atts(array(
        'question' => '',
        'option_a' => '',
        'option_b' => '',
        'option_c' => '',
        'option_d' => '',
        'correct' => 'A',
        'explanation' => ''
    ), $atts, $tag);

    foreach ($atts as $key => &$value) {
        $value = process_variables_php($value, $variables);
    }

    $options = array('A' => $atts['option_a'], 'B' => $atts['option_b'], 'C' => $atts['option_c'], 'D' => $atts['option_d']);
    $quiz_id = uniqid();
    $encoded_correct = 'ENC:' . base64_encode($atts['correct']);
    return '<div class="quiz-box" id="quiz-box-' . $quiz_id . '" data-correct="' . esc_attr($encoded_correct) . '" data-options="' . esc_attr(json_encode($options)) . '" data-type="single-choice">
            <div class="question-section"><h5>' . wp_kses($atts['question'], quiz_display_allowed_html()) . '</h5></div>
            <div class="options-section">' . generate_options_html($options, $quiz_id) . '</div>
            <div class="explanation" style="display:none;"><div class="explanation-content">' . wp_kses($atts['explanation'], quiz_display_allowed_html()) . '</div></div>
        </div>';
}
add_shortcode('quiz_question', 'quiz_question_shortcode');

// Shortcode cho câu hỏi trắc nghiệm đúng/sai
function quiz_question_T_F_shortcode($atts, $content = null, $tag = '', &$variables = array()) {
    $atts = shortcode_atts(array(
        'question' => '',
        'option_a' => '',
        'option_b' => '',
        'option_c' => '',
        'option_d' => '',
        'correct' => '',
        'explanation' => ''
    ), $atts, $tag);

    foreach ($atts as $key => &$value) {
        $value = process_variables_php($value, $variables);
    }

    $options = array('A' => $atts['option_a'], 'B' => $atts['option_b'], 'C' => $atts['option_c'], 'D' => $atts['option_d']);
    $quiz_id = uniqid();
    $encoded_correct = 'ENC:' . base64_encode($atts['correct']);
    return '<div class="quiz-box quiz-box-tf" id="quiz-box-tf-' . $quiz_id . '" data-correct="' . esc_attr($encoded_correct) . '" data-options="' . esc_attr(json_encode($options)) . '" data-type="true-false">
                <div class="question-section"><h5>' . wp_kses($atts['question'], quiz_display_allowed_html()) . '</h5></div>
                <div class="options-section">' . generate_true_false_options_html($options, $quiz_id) . '</div>
                <div class="explanation" style="display:none;"><div class="explanation-content">' . wp_kses($atts['explanation'], quiz_display_allowed_html()) . '</div></div>
            </div>';
}
add_shortcode('quiz_question_T_F', 'quiz_question_T_F_shortcode');

// Shortcode cho câu hỏi trả lời ngắn
function quiz_question_short_answer_shortcode($atts, $content = null, $tag = '', &$variables = array()) {
    $atts = shortcode_atts(array(
        'question' => '',
        'correct' => '',
        'explanation' => ''
    ), $atts, $tag);

    foreach ($atts as $key => &$value) {
        $value = process_variables_php($value, $variables);
    }

    $quiz_id = uniqid();
    $encoded_correct = 'ENC:' . base64_encode($atts['correct']);
    return '<div class="quiz-box quiz-box-sa" id="quiz-box-sa-' . $quiz_id . '" data-correct="' . esc_attr($encoded_correct) . '" data-type="short-answer">
                <div class="question-section"><h5>' . wp_kses($atts['question'], quiz_display_allowed_html()) . '</h5></div>
                <div class="answer-section"><input type="text" name="short_answer_' . $quiz_id . '" id="short_answer_' . $quiz_id . '" class="short-answer-input" maxlength="4"></div>
                <div class="explanation" style="display:none;"><div class="explanation-content">' . wp_kses($atts['explanation'], quiz_display_allowed_html()) . '</div></div>
            </div>';
}
add_shortcode('quiz_question_TLN', 'quiz_question_short_answer_shortcode');

// Thêm menu quản trị
function quiz_display_admin_menu() {
    add_menu_page('Quiz Display Settings', 'Quiz Display', 'manage_options', 'quiz-display-settings', 'quiz_display_settings_page', 'dashicons-clipboard');
    add_submenu_page('quiz-display-settings', 'Xem Điểm', 'Xem Điểm', 'manage_options', 'quiz-results', 'quiz_display_results_page');
    add_submenu_page('quiz-display-settings', 'Thống Kê Bài Thi', 'Thống Kê Bài Thi', 'manage_options', 'quiz-stats', 'quiz_display_stats_page');
}
add_action('admin_menu', 'quiz_display_admin_menu');

// Trang cài đặt plugin
function quiz_display_settings_page() {
    ?>
    <div class="wrap">
        <h2><?php echo esc_html(get_admin_page_title()); ?></h2>
        <p><strong>Lưu ý:</strong> Để xáo trộn câu hỏi và đáp án, sử dụng các thuộc tính trong shortcode <code>[quiz_set]</code>.</p>
        <p>Ví dụ:</p>
        <pre><code>[quiz_set type="exam" time="15" single_choice_socau="2" true_false_socau="2" short_answer_socau="3" single_choice_tron="y,y" true_false_tron="y,n" short_answer_tron="y"]...[/quiz_set]</code></pre>
        <ul>
            <li><code>type="practice|exam"</code>: Loại bài quiz (luyện tập hoặc kiểm tra).</li>
            <li><code>time="15"</code>: Thời gian làm bài (phút), chỉ áp dụng cho <code>type="exam"</code>.</li>
            <li><code>single_choice_socau="2"</code>: Số câu trắc nghiệm 1 đáp án sẽ được chọn ngẫu nhiên (nếu để 0 là lấy hết).</li>
            <li><code>true_false_socau="2"</code>: Số câu Đúng/Sai sẽ được chọn ngẫu nhiên (nếu để 0 là lấy hết).</li>
            <li><code>short_answer_socau="3"</code>: Số câu trả lời ngắn sẽ được chọn ngẫu nhiên (nếu để 0 là lấy hết).</li>
            <li><code>single_choice_tron="y,y"</code>: Xáo trộn câu hỏi (chữ cái đầu: y/n), xáo trộn đáp án (chữ cái sau: y/n) cho phần trắc nghiệm 1 đáp án.</li>
            <li><code>true_false_tron="y,n"</code>: Xáo trộn câu hỏi (y/n), xáo trộn thứ tự các mệnh đề (y/n) cho phần Đúng/Sai.</li>
            <li><code>short_answer_tron="y"</code>: Xáo trộn câu hỏi (y/n) cho phần trả lời ngắn.</li>
        </ul>
         <p><em>(Hiện tại trang này chỉ có chức năng hiển thị hướng dẫn)</em></p>
         <?php
            // Nếu bạn muốn thêm các cài đặt thực sự (options) thì code sẽ nằm ở đây.
            // Ví dụ: register_setting(), settings_fields(), do_settings_sections()...
         ?>
    </div>
    <?php // Đóng thẻ div.wrap ?>
    <?php
}
// Hàm lưu điểm và đáp án vào CSDL
add_action('wp_ajax_save_quiz_results', 'save_quiz_results');
add_action('wp_ajax_nopriv_save_quiz_results', 'save_quiz_results');
function save_quiz_results() {
    global $wpdb;

    $quiz_id = sanitize_text_field($_POST['quiz_id'] ?? '');
    $score_part_1 = floatval($_POST['score_part_1'] ?? 0);
    $score_part_2 = floatval($_POST['score_part_2'] ?? 0);
    $score_part_3 = floatval($_POST['score_part_3'] ?? 0);
    $total_score = floatval($_POST['total_score'] ?? 0);
    $quiz_type = sanitize_text_field($_POST['type'] ?? 'practice');
    $start_time = sanitize_text_field($_POST['start_time'] ?? date('Y-m-d H:i:s'));
    $end_time = sanitize_text_field($_POST['end_time'] ?? date('Y-m-d H:i:s'));
    $student_name = sanitize_text_field($_POST['student_name'] ?? '');
    $post_id = intval($_POST['post_id'] ?? 0);
    $answers = json_decode(stripslashes($_POST['answers'] ?? '[]'), true);

    if (empty($quiz_id)) {
        wp_send_json_error('Thiếu ID bài kiểm tra.');
        return;
    }

    $user_id = get_current_user_id();

    if ($quiz_type === 'exam') {
        // Ghi log để debug student_name
        error_log('Student Name: ' . $student_name);

        if (empty($student_name)) {
            wp_send_json_error('Vui lòng nhập tên của bạn.');
            return;
        }

        if (empty($post_id)) {
            wp_send_json_error('Không tìm thấy ID bài viết.');
            return;
        }

        $table_name = $wpdb->prefix . 'quiz_results';
        $data = array(
            'user_id' => $user_id,
            'quiz_id' => $quiz_id,
            'score_part_1' => $score_part_1,
            'score_part_2' => $score_part_2,
            'score_part_3' => $score_part_3,
            'total_score' => $total_score,
            'start_time' => $start_time,
            'end_time' => $end_time,
            'student_name' => $student_name,
            'post_id' => $post_id,
        );

        $result = $wpdb->insert($table_name, $data);
        if ($result === false) {
            wp_send_json_error('Lỗi khi lưu điểm: ' . $wpdb->last_error);
            return;
        }

        $result_id = $wpdb->insert_id;
        $answers_table = $wpdb->prefix . 'quiz_answers';

        foreach ($answers as $answer) {
            $wpdb->insert($answers_table, array(
                'result_id' => $result_id,
                'question_id' => sanitize_text_field($answer['question_id']),
                'question_type' => sanitize_text_field($answer['type']),
                'student_answer' => sanitize_text_field($answer['answer']),
                'is_correct' => intval($answer['is_correct'])
            ));
        }
    }

    $response_data = array(
        'student_name' => $student_name,
        'start_time' => $start_time,
        'end_time' => $end_time
    );

    if ($user_id && empty($response_data['student_name'])) {
        $user_info = get_userdata($user_id);
        $response_data['student_name'] = $user_info->display_name;
    }

    wp_send_json_success($response_data);
}

// Hàm hiển thị trang xem điểm
function quiz_display_results_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_results';

    if (isset($_POST['delete_selected']) && !empty($_POST['selected_results'])) {
        $selected_ids = array_map('intval', $_POST['selected_results']);
        $wpdb->query("DELETE FROM $table_name WHERE id IN (" . implode(',', $selected_ids) . ")");
        echo '<div class="notice notice-success is-dismissible"><p>Đã xóa các kết quả được chọn.</p></div>';
    }

    $post_id_filter = isset($_GET['post_id_filter']) ? intval($_GET['post_id_filter']) : '';
    $per_page = 50;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    $where = '';
    if (!empty($post_id_filter)) {
        $where = $wpdb->prepare(" WHERE post_id = %d", $post_id_filter);
    }

    $total_items = $wpdb->get_var("SELECT COUNT(*) FROM $table_name" . $where);
    $results = $wpdb->get_results(
        $wpdb->prepare("SELECT * FROM $table_name" . $where . " ORDER BY end_time DESC LIMIT %d OFFSET %d", $per_page, $offset)
    );

    $total_pages = ceil($total_items / $per_page);

    ?>
    <div class="wrap">
        <h2>Kết quả thi</h2>
        <form method="get" action="">
            <input type="hidden" name="page" value="quiz-results">
            <p class="search-box">
                <label for="post_id_filter">Lọc theo Post ID:</label>
                <input type="number" name="post_id_filter" id="post_id_filter" value="<?php echo esc_attr($post_id_filter); ?>" min="0">
                <input type="submit" class="button" value="Lọc">
            </p>
        </form>
        <form method="post" action="">
            <div class="tablenav top">
                <div class="alignleft actions">
                    <input type="submit" name="delete_selected" class="button action" value="Xóa các mục đã chọn" onclick="return confirm('Bạn có chắc chắn muốn xóa các kết quả đã chọn?');">
                </div>
            </div>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 30px;"><input type="checkbox" id="select_all"></th>
                        <th>ID</th>
                        <th>User ID</th>
                        <th>Tên học sinh</th>
                        <th>Quiz ID</th>
                        <th>Post ID</th>
                        <th>Điểm phần I</th>
                        <th>Điểm phần II</th>
                        <th>Điểm phần III</th>
                        <th>Tổng điểm</th>
                        <th>Thời gian bắt đầu</th>
                        <th>Thời gian kết thúc</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)) : ?>
                        <tr><td colspan="12">Không có kết quả nào.</td></tr>
                    <?php else : ?>
                        <?php foreach ($results as $result) : ?>
                            <tr>
                                <td><input type="checkbox" name="selected_results[]" value="<?php echo esc_attr($result->id); ?>"></td>
                                <td><?php echo esc_html($result->id); ?></td>
                                <td><?php echo esc_html($result->user_id); ?></td>
                                <td><?php echo esc_html($result->student_name ?: ($result->user_id ? get_userdata($result->user_id)->display_name : 'N/A')); ?></td>
                                <td><?php echo esc_html($result->quiz_id); ?></td>
                                <td><?php echo esc_html($result->post_id ?: 'N/A'); ?></td>
                                <td><?php echo esc_html($result->score_part_1); ?></td>
                                <td><?php echo esc_html($result->score_part_2); ?></td>
                                <td><?php echo esc_html($result->score_part_3); ?></td>
                                <td><?php echo esc_html($result->total_score); ?></td>
                                <td><?php echo esc_html(date('d/m/Y H:i:s', strtotime($result->start_time . ' UTC') + 7 * 3600)); ?></td>
                                <td><?php echo esc_html(date('d/m/Y H:i:s', strtotime($result->end_time . ' UTC') + 7 * 3600)); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <div class="tablenav bottom">
                <div class="tablenav-pages">
                    <?php
                    echo paginate_links(array(
                        'base' => add_query_arg('paged', '%#%'),
                        'format' => '',
                        'prev_text' => __('« Trước'),
                        'next_text' => __('Tiếp »'),
                        'total' => $total_pages,
                        'current' => $current_page,
                        'add_args' => !empty($post_id_filter) ? array('post_id_filter' => $post_id_filter) : false
                    ));
                    ?>
                    <span class="displaying-num"><?php echo esc_html($total_items); ?> mục</span>
                </div>
            </div>
        </form>
        <script>
            document.getElementById('select_all').addEventListener('change', function() {
                const checkboxes = document.querySelectorAll('input[name="selected_results[]"]');
                checkboxes.forEach(checkbox => checkbox.checked = this.checked);
            });
        </script>
    </div>
    <?php
}

// Trang thống kê bài thi
function quiz_display_stats_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_results';
    $answers_table = $wpdb->prefix . 'quiz_answers';

 
    // Xem chi tiết đáp án
    $view_details = isset($_GET['view_details']) ? intval($_GET['view_details']) : 0;
    if ($view_details) {
        $result = $wpdb->get_row($wpdb->prepare(
            "SELECT r.*, p.post_title 
             FROM $table_name r 
             JOIN {$wpdb->posts} p ON r.post_id = p.ID 
             WHERE r.id = %d",
            $view_details
        ));

        if ($result) {
            $answers = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $answers_table WHERE result_id = %d ORDER BY id",
                $view_details
            ));

            ?>
            <div class="wrap">
                <h2>Chi tiết bài thi của <?php echo esc_html($result->student_name); ?></h2>
                <p><strong>Tên bài thi:</strong> <?php echo esc_html($result->post_title); ?> (Post ID: <?php echo $result->post_id; ?>)</p>
                <p><strong>Điểm:</strong> Phần I: <?php echo $result->score_part_1; ?>, Phần II: <?php echo $result->score_part_2; ?>, Phần III: <?php echo $result->score_part_3; ?>, Tổng: <?php echo $result->total_score; ?></p>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Câu hỏi ID</th>
                            <th>Loại câu hỏi</th>
                            <th>Đáp án học sinh</th>
                            <th>Kết quả</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($answers as $answer) : ?>
                            <tr>
                                <td><?php echo esc_html($answer->question_id); ?></td>
                                <td><?php echo esc_html($answer->question_type); ?></td>
                                <td><?php echo esc_html($answer->student_answer); ?></td>
                                <td><?php echo $answer->is_correct ? 'Đúng' : 'Sai'; ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
                <p><a href="?page=quiz-stats&post_id=<?php echo $result->post_id; ?>" class="button">Quay lại</a></p>
            </div>
            <?php
            return;
        }
    }

    // Hiển thị danh sách bài thi
    $post_id_filter = isset($_GET['post_id']) ? intval($_GET['post_id']) : '';
    if ($post_id_filter) {
        $results = $wpdb->get_results($wpdb->prepare(
            "SELECT r.*, p.post_title 
             FROM $table_name r 
             JOIN {$wpdb->posts} p ON r.post_id = p.ID 
             WHERE r.post_id = %d 
             ORDER BY r.student_name",
            $post_id_filter
        ));
// Xử lý xuất CSV
	$export_url = admin_url('admin-ajax.php?action=export_quiz_csv&post_id=' . $post_id_filter);
			
        $post = get_post($post_id_filter);
        ?>
        <div class="wrap">
            <h2>Kết quả bài thi: <?php echo esc_html($post->post_title); ?> (Post ID: <?php echo $post_id_filter; ?>)</h2>
            <p><a href="?page=quiz-stats" class="button">Quay lại danh sách bài thi</a> 
            	<a href="<?php echo esc_url($export_url); ?>" class="button">Xuất CSV</a>
			</p>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Tên học sinh</th>
                        <th>Điểm phần I</th>
                        <th>Điểm phần II</th>
                        <th>Điểm phần III</th>
                        <th>Tổng điểm</th>
                        <th>Thời gian nộp bài</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($results)) : ?>
                        <tr><td colspan="7">Không có kết quả nào.</td></tr>
                    <?php else : ?>
                        <?php foreach ($results as $result) : ?>
                            <tr>
                                <td><?php echo esc_html($result->student_name); ?></td>
                                <td><?php echo esc_html($result->score_part_1); ?></td>
                                <td><?php echo esc_html($result->score_part_2); ?></td>
                                <td><?php echo esc_html($result->score_part_3); ?></td>
                                <td><?php echo esc_html($result->total_score); ?></td>
                                <td><?php echo esc_html(date('d/m/Y H:i:s', strtotime($result->end_time . ' UTC') + 7 * 3600)); ?></td>
                                <td><a href="?page=quiz-stats&view_details=<?php echo $result->id; ?>">Xem chi tiết</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    } else {
        // Lấy danh sách các bài thi type="exam"
        $posts = $wpdb->get_results(
            "SELECT DISTINCT p.ID, p.post_title 
             FROM {$wpdb->posts} p 
             JOIN $table_name r ON p.ID = r.post_id 
             WHERE p.post_content LIKE '%[quiz_set type=\"exam\"%'"
        );

        ?>
        <div class="wrap">
            <h2>Thống kê bài thi</h2>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th>Post ID</th>
                        <th>Tên bài thi</th>
                        <th>Hành động</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($posts)) : ?>
                        <tr><td colspan="3">Không có bài thi nào.</td></tr>
                    <?php else : ?>
                        <?php foreach ($posts as $post) : ?>
                            <tr>
                                <td><?php echo esc_html($post->ID); ?></td>
                                <td><?php echo esc_html($post->post_title); ?></td>
                                <td><a href="?page=quiz-stats&post_id=<?php echo $post->ID; ?>">Xem thống kê</a></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php
    }
}

// Hàm tạo HTML cho các options
function generate_options_html($options, $quiz_id) {
    $output = '';
    foreach ($options as $key => $value) {
        $output .= '<div class="option" style="display: flex; align-items: center;">
                        <input type="radio" name="question_' . $quiz_id . '" id="question_' . $quiz_id . '_option_' . $key . '" value="' . $key . '" style="margin-right: 5px;">
                        <label for="question_' . $quiz_id . '_option_' . $key . '" style="margin: 0;"><strong>' . $key . '.</strong> ' . $value . '</label>
                    </div>';
    }
    return $output;
}

function generate_true_false_options_html($options, $quiz_id) {
    $output = '';
    $option_letters = array('a', 'b', 'c', 'd');
    $i = 0;
    foreach ($options as $key => $value) {
        $output .= '<div class="option-tf">
                <label class="option-tf-label" for="question_tf_' . $quiz_id . '_option_' . $key . '">' . $option_letters[$i] . ') ' . $value . '</label>
                <div class="tf-buttons">
                    <input type="radio" name="question_tf_' . $quiz_id . '_option_' . $key . '" id="question_tf_' . $quiz_id . '_option_' . $key . '_true" value="true">
                    <label class="true-label" for="question_tf_' . $quiz_id . '_option_' . $key . '_true">Đ</label>
                    <input type="radio" name="question_tf_' . $quiz_id . '_option_' . $key . '" id="question_tf_' . $quiz_id . '_option_' . $key . '_false" value="false">
                    <label class="false-label" for="question_tf_' . $quiz_id . '_option_' . $key . '_false">S</label>
                </div>
            </div>';
        $i++;
    }
    return $output;
}

// Thêm vào file plugin của bạn

// Đăng ký endpoint AJAX cho export CSV
add_action('wp_ajax_export_quiz_csv', 'quiz_export_csv');
function quiz_export_csv() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'quiz_results';
    
    // Kiểm tra nonce và quyền truy cập
    if (!current_user_can('manage_options')) {
        wp_die('Unauthorized access');
    }
    
    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : 0;
    if (empty($post_id)) {
        wp_die('Missing Post ID');
    }
    
    // Truy vấn dữ liệu
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT r.*, p.post_title 
         FROM $table_name r 
         LEFT JOIN {$wpdb->posts} p ON r.post_id = p.ID 
         WHERE r.post_id = %d 
         ORDER BY r.student_name",
        $post_id
    ));
    
    if (empty($results)) {
        wp_die('No data found');
    }
    
    // Xóa tất cả output trước đó
    if (ob_get_level()) {
        ob_end_clean();
    }
    
    // Thiết lập headers
    $filename = 'quiz_results_post_' . $post_id . '_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Transfer-Encoding: binary');
    header('Expires: 0');
    header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
    header('Pragma: public');
    
    // Tạo file CSV
    $output = fopen('php://output', 'w');
    fputs($output, "\xEF\xBB\xBF"); // BOM cho UTF-8
    
    // Tiêu đề
    fputcsv($output, array(
        'ID', 'Tên học sinh', 'Quiz ID', 'Post ID', 'Tên bài thi',
        'Điểm phần I', 'Điểm phần II', 'Điểm phần III', 'Tổng điểm',
        'Thời gian bắt đầu', 'Thời gian kết thúc'
    ));
    
    // Dữ liệu
    foreach ($results as $result) {
        fputcsv($output, array(
            $result->id,
            $result->student_name,
            $result->quiz_id,
            $result->post_id,
            $result->post_title ?: 'N/A',
            $result->score_part_1,
            $result->score_part_2,
            $result->score_part_3,
            $result->total_score,
            date('d/m/Y H:i:s', strtotime($result->start_time . ' UTC') + 7 * 3600),
            date('d/m/Y H:i:s', strtotime($result->end_time . ' UTC') + 7 * 3600)
        ));
    }
    
    fclose($output);
    die();
}
