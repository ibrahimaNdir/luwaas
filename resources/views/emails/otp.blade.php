<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification Luwaas</title>
</head>
<body style="font-family: Arial, sans-serif; background-color: #f3f4f6; margin: 0; padding: 20px;">
    <div style="max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 40px; border-radius: 8px; box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);">
        <div style="text-align: center; margin-bottom: 30px;">
            <svg viewBox="0 0 120 120" width="60" height="60" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M 57 20 L 12 65 L 32 85 L 57 60 Z" fill="#1F2937" />
              <path d="M 63 20 L 108 65 L 88 85 L 63 60 Z" fill="#0F766E" />
            </svg>
            <h1 style="color: #1f2937; margin-top: 15px;">Vérification Luwaas</h1>
        </div>
        
        <p style="color: #4b5563; font-size: 16px;">Bonjour,</p>
        <p style="color: #4b5563; font-size: 16px;">Voici votre code de vérification pour accéder à votre compte Luwaas :</p>
        
        <div style="text-align: center; margin: 30px 0;">
            <span style="font-size: 32px; font-weight: bold; color: #0f766e; background-color: #e0f2f1; padding: 10px 20px; border-radius: 5px; letter-spacing: 5px;">{{ $otp }}</span>
        </div>
        
        <p style="color: #4b5563; font-size: 16px;">Ce code est valable pour les 10 prochaines minutes.</p>
        <p style="color: #6b7280; font-size: 14px;">Si vous n'êtes pas à l'origine de cette demande, vous pouvez ignorer cet email.</p>
        
        <div style="margin-top: 40px; border-top: 1px solid #e5e7eb; padding-top: 20px; text-align: center; color: #9ca3af; font-size: 12px;">
            &copy; {{ date('Y') }} Luwaas. Tous droits réservés.
        </div>
    </div>
</body>
</html>