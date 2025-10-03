<?php
/**
 * Functions Library
 * 
 * This file contains reusable functions for the Movie Booking System.
 */

/**
 * Format movie duration from minutes to hours and minutes
 * 
 * @param int $minutes Duration in minutes
 * @return string Formatted duration (e.g., "2h 30m")
 */
function formatDuration($minutes) {
    $hours = floor($minutes / 60);
    $mins = $minutes % 60;
    
    if ($hours > 0) {
        return $hours . "h " . ($mins > 0 ? $mins . "m" : "");
    } else {
        return $mins . "m";
    }
}

/**
 * Generate a unique booking reference
 * 
 * @return string Unique booking reference
 */
function generateBookingReference() {
    $prefix = TICKET_PREFIX;
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    
    return $prefix . $timestamp . $random;
}

/**
 * Validate date format (YYYY-MM-DD)
 * 
 * @param string $date Date string to validate
 * @return bool True if valid, false otherwise
 */
function validateDate($date) {
    $d = DateTime::createFromFormat('Y-m-d', $date);
    return $d && $d->format('Y-m-d') === $date;
}

/**
 * Validate time format (HH:MM:SS or HH:MM)
 * 
 * @param string $time Time string to validate
 * @return bool True if valid, false otherwise
 */
function validateTime($time) {
    $pattern1 = '/^([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/'; // HH:MM:SS
    $pattern2 = '/^([01][0-9]|2[0-3]):([0-5][0-9])$/'; // HH:MM
    
    return preg_match($pattern1, $time) || preg_match($pattern2, $time);
}

/**
 * Generate a unique filename for uploaded files
 * 
 * @param string $extension File extension (without dot)
 * @return string Unique filename
 */
function generateUniqueFilename($extension) {
    $timestamp = time();
    $random = mt_rand(1000, 9999);
    
    return 'movie_' . $timestamp . '_' . $random . '.' . $extension;
}

/**
 * Format file size in human-readable format
 * 
 * @param int $bytes File size in bytes
 * @return string Formatted file size (e.g., "2.5 MB")
 */
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

/**
 * Sanitize and validate input data
 * 
 * @param string $data Data to sanitize
 * @return string Sanitized data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Check if a string is a valid email address
 * 
 * @param string $email Email address to validate
 * @return bool True if valid, false otherwise
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/**
 * Generate pagination links
 * 
 * @param int $current_page Current page number
 * @param int $total_pages Total number of pages
 * @param string $url_pattern URL pattern with %d placeholder for page number
 * @return string HTML for pagination links
 */
function generatePagination($current_page, $total_pages, $url_pattern) {
    if ($total_pages <= 1) {
        return '';
    }
    
    $html = '<nav aria-label="Page navigation"><ul class="pagination justify-content-center">';
    
    // Previous page link
    if ($current_page > 1) {
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, 1) . '" aria-label="First"><span aria-hidden="true">&laquo;&laquo;</span></a></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $current_page - 1) . '" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#" aria-label="First"><span aria-hidden="true">&laquo;&laquo;</span></a></li>';
        $html .= '<li class="page-item disabled"><a class="page-link" href="#" aria-label="Previous"><span aria-hidden="true">&laquo;</span></a></li>';
    }
    
    // Page number links
    $start = max(1, $current_page - 2);
    $end = min($total_pages, $current_page + 2);
    
    for ($i = $start; $i <= $end; $i++) {
        if ($i == $current_page) {
            $html .= '<li class="page-item active"><a class="page-link" href="#">' . $i . '</a></li>';
        } else {
            $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $i) . '">' . $i . '</a></li>';
        }
    }
    
    // Next page link
    if ($current_page < $total_pages) {
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $current_page + 1) . '" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
        $html .= '<li class="page-item"><a class="page-link" href="' . sprintf($url_pattern, $total_pages) . '" aria-label="Last"><span aria-hidden="true">&raquo;&raquo;</span></a></li>';
    } else {
        $html .= '<li class="page-item disabled"><a class="page-link" href="#" aria-label="Next"><span aria-hidden="true">&raquo;</span></a></li>';
        $html .= '<li class="page-item disabled"><a class="page-link" href="#" aria-label="Last"><span aria-hidden="true">&raquo;&raquo;</span></a></li>';
    }
    
    $html .= '</ul></nav>';
    
    return $html;
}

/**
 * Get upcoming movies
 * 
 * @param PDO $pdo PDO database connection
 * @param int $limit Number of movies to return
 * @return array Array of upcoming movies
 */
function getUpcomingMovies($pdo, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.id, m.title, m.poster, m.release_date, m.status
            FROM movies m
            WHERE m.status = 'coming_soon' 
            AND m.release_date > CURDATE()
            ORDER BY m.release_date ASC
            LIMIT ?
        ");
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Check if a showtime has any bookings
 * 
 * @param PDO $pdo PDO database connection
 * @param int $showtime_id Showtime ID
 * @return bool True if has bookings, false otherwise
 */
function showtimeHasBookings($pdo, $showtime_id) {
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM bookings WHERE showtime_id = ?");
        $stmt->execute([$showtime_id]);
        return $stmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Get top-rated movies
 * 
 * @param PDO $pdo PDO database connection
 * @param int $limit Number of movies to return
 * @return array Array of top-rated movies
 */
function getTopRatedMovies($pdo, $limit = 5) {
    try {
        $stmt = $pdo->prepare("
            SELECT m.id, m.title, m.poster, AVG(r.rating) as avg_rating, COUNT(r.id) as rating_count
            FROM movies m
            JOIN ratings r ON m.id = r.movie_id
            WHERE m.status = 'active'
            GROUP BY m.id
            HAVING rating_count >= 3
            ORDER BY avg_rating DESC, rating_count DESC
            LIMIT ?
        ");
        $stmt->bindParam(1, $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        return [];
    }
}

/**
 * Check if a seat is available for booking
 * 
 * @param PDO $pdo PDO database connection
 * @param int $showtime_id Showtime ID
 * @param int $row Seat row
 * @param int $column Seat column
 * @return bool True if available, false if booked
 */
function isSeatAvailable($pdo, $showtime_id, $row, $column) {
    try {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM booking_seats bs
            JOIN bookings b ON bs.booking_id = b.id
            WHERE b.showtime_id = ? AND bs.seat_row = ? AND bs.seat_column = ? AND b.status != 'cancelled'
        ");
        $stmt->execute([$showtime_id, $row, $column]);
        return $stmt->fetchColumn() == 0;
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Format currency amount
 * 
 * @param float $amount Amount to format
 * @return string Formatted amount
 */
function formatCurrency($amount) {
    return '₹' . number_format($amount, 2);
}
