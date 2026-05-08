Sending emails from localhost in XAMPP requires configuring PHP to use an SMTP server (like Gmail) to bypass spam filters. The most common method involves editing php.ini and sendmail.ini to route mail through your personal email account.
Step 1: Configure php.ini (PHP Configuration)

1. Open the XAMPP Control Panel and click Config next to Apache, then select php.ini.
2. Find the [mail function] section and update it with these settings:
   ini
   SMTP=smtp.gmail.com
   smtp_port=587
   sendmail_from = your_email@gmail.com
   sendmail_path = "\"C:\xampp\sendmail\sendmail.exe\" -t"

# Ensure extension=php_openssl.dll is uncommented

Use code with caution.

Step 2: Configure sendmail.ini (SMTP Settings)

1. Navigate to C:\xampp\sendmail\ and open sendmail.ini in a text editor.
2. Update the file with your Gmail credentials:
   ini
   smtp_server=smtp.gmail.com
   smtp_port=587
   smtp_ssl=tls
   auth_username=your_email@gmail.com
   auth_password=your_app_password
   force_sender=your_email@gmail.com
   hostname=localhost

Step 3: Set Up Gmail (App Password)
Because Google no longer supports "less secure apps," you must use an App Password. [https://myaccount.google.com/security]

1. Go to your Google Account Security settings.
2. Ensure 2-Step Verification is on.
3. Create an "App Password" (name it "XAMPP" or "Localhost").
4. Copy the 16-character password and use it in the auth_password field in sendmail.ini.
Step 4: Restart Apache
Restart the Apache server in the XAMPP Control Panel to apply changes.
Step 5: Test the Email
Create a file named mailtest.php in your htdocs folder to test:
php
<?php
$to = "recipient@example.com";
$subject = "Localhost Test";
$message = "Hello, this is a test email from XAMPP!";
$headers = "From: your_email@gmail.com";

if(mail($to, $subject, $message, $headers)) {
echo "Email sent successfully!";
} else {
echo "Email sending failed.";
}
?>
Use code with caution.
Alternatives
• Mailtrap: A fake SMTP server for testing (highly recommended to avoid sending real emails).
• PHPMailer: A library that offers more control than the default mail() function.
