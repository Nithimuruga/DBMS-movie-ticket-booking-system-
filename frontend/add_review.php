<?php
require_once '../config/config.php';
require_once '../includes/functions.php';
require_once '../includes/auth_check.php';

// Check if the form is submitted
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit;
}

$errors = [];
$movie_id = isset($_POST['movie_id']) ? (int)$_POST['movie_id'] : 0;
$rating = isset($_POST['rating']) ? (int)$_POST['rating'] : 0;
$comment = isset($_POST['comment']) ? trim($_POST['comment']) : '';

// Validate input
if (empty($movie_id)) {
    $errors[] = "Invalid movie selected";
}

if (empty($rating) || $rating < 1 || $rating > 5) {
    $errors[] = "Please select a valid rating between 1 and 5";
}

if (empty($comment)) {
    $errors[] = "Please add a comment with your review";
}

// Check if user has already rated this movie
try {
    $stmt = $pdo->prepare("SELECT id FROM ratings WHERE user_id = ? AND movie_id = ?");
    $stmt->execute([$_SESSION['user_id'], $movie_id]);
    
    if ($stmt->rowCount() > 0) {
        // Update existing rating
        $stmt = $pdo->prepare("
            UPDATE ratings 
            SET rating = ?, comment = ?, updated_at = NOW() 
            WHERE user_id = ? AND movie_id = ?
        ");
        $stmt->execute([$rating, $comment, $_SESSION['user_id'], $movie_id]);
        
        $_SESSION['review_message'] = "Your review has been updated successfully!";
    } else {
        // Insert new rating
        $stmt = $pdo->prepare("
            INSERT INTO ratings (user_id, movie_id, rating, comment, created_at) 
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([$_SESSION['user_id'], $movie_id, $rating, $comment]);
        
        $_SESSION['review_message'] = "Your review has been added successfully!";
    }
    
    // Redirect back to movie details page
    header("Location: movie_details.php?id=" . $movie_id);
    exit;
} catch (PDOException $e) {
    $errors[] = "Error saving review: " . $e->getMessage();
}

// If there are errors, redirect back with error message
if (!empty($errors)) {
    $_SESSION['review_errors'] = $errors;
    header("Location: movie_details.php?id=" . $movie_id);
    exit;
}
