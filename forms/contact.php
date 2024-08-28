<?php
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Retrieve form input
    $name = htmlspecialchars($_POST['name']);
    $email = htmlspecialchars($_POST['email']);
    $subject = htmlspecialchars($_POST['subject']);
    $message = htmlspecialchars($_POST['message']);

    // Validate email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['status' => 'error', 'message' => 'Invalid email address.']);
        exit;
    }

    // Set email parameters
    $to = 'azizbeniych01@gmail.com';  // Replace with your email address
    $headers = "From: $name <$email>\r\n";
    $headers .= "Reply-To: $email\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

    // Compose the email content
    $email_subject = "New message from $name: $subject";
    $email_body = "<h2>Contact Form Submission</h2>
                   <p><strong>Name:</strong> $name</p>
                   <p><strong>Email:</strong> $email</p>
                   <p><strong>Subject:</strong> $subject</p>
                   <p><strong>Message:</strong><br>$message</p>";

    // Send the email
    if (mail($to, $email_subject, $email_body, $headers)) {
        echo json_encode(['status' => 'success', 'message' => 'Your message has been sent. Thank you!']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'There was an error sending your message.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
}
?>