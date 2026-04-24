<?php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require 'vendor/autoload.php';

function sendPasswordResetEmail($toEmail, $toName, $username, $newPassword) {
    $mail = new PHPMailer(true);

    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        $mail->Username   = 'iLet4You@gmail.com'; 
        $mail->Password   = 'zttl gvll gzif godd';
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('iLet4You@gmail.com', 'iLet4You');
        $mail->addAddress($toEmail, $toName);

        $mail->isHTML(true);
        $mail->Subject = 'Password Reset - iLet4You';
        $mail->Body    = "
           <div style='font-family: Arial, sans-serif; padding: 20px;'>
               <h2 style='color: #2c3e50;'>Password Reset</h2>

               <p>Hello <strong>{$toName}</strong>,</p>

               <p>Your username is:</p>
               <div style='background: #f4f4f4; padding: 10px; border-radius: 6px;
                           font-size: 18px; text-align: center; font-weight: bold;'>
                   {$username}
               </div>

               <p>Your password has been reset. Your new password is:</p>

               <div style='background: #f4f4f4; padding: 15px; border-radius: 8px; font-size: 22px; 
                           letter-spacing: 4px; text-align: center; font-weight: bold; color: #e74c3c;'>
                   {$newPassword}
               </div>

               <p style='margin-top: 20px; color: #7f8c8d; font-size: 13px;'>
                   Remember, you have 30 days then you will need to reset it again.
               </p>
           </div>
       ";
       $mail->AltBody = "Hello {$toName}, your username is {$username}, and your new password is: {$newPassword}";
        $mail->send();
        return true;
    } catch (Exception $e) {
        error_log("Mailer Error: " . $mail->ErrorInfo);
        return false;
    }
}
?>