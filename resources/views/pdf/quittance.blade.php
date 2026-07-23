<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Quittance de Loyer - {{ $paiement->reference ?? $paiement->id }}</title>
    <style>
        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 14px;
            color: #333;
            line-height: 1.5;
        }
        .header {
            text-align: center;
            margin-bottom: 40px;
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
        }
        .title {
            font-size: 24px;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
        }
        .details-container {
            width: 100%;
            margin-bottom: 30px;
        }
        .details-container td {
            vertical-align: top;
            width: 50%;
        }
        .box {
            border: 1px solid #ccc;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .box-title {
            font-weight: bold;
            margin-bottom: 10px;
            border-bottom: 1px solid #eee;
            padding-bottom: 5px;
        }
        .amount {
            font-size: 18px;
            font-weight: bold;
            text-align: right;
            margin-top: 30px;
            padding: 15px;
            background-color: #f9f9f9;
            border: 1px solid #ddd;
        }
        .footer {
            margin-top: 50px;
            font-size: 12px;
            text-align: center;
            color: #777;
        }
        .signature {
            margin-top: 60px;
            text-align: right;
        }
    </style>
</head>
<body>

    <div class="header">
        <h1 class="title">Quittance de Loyer</h1>
        <p>Reçu N° {{ $paiement->id }} - Émis le {{ \Carbon\Carbon::now()->format('d/m/Y') }}</p>
    </div>

    <table class="details-container">
        <tr>
            <td style="padding-right: 10px;">
                <div class="box">
                    <div class="box-title">Bailleur</div>
                    {{ $paiement->bail->proprietaire->user->prenom ?? '' }} {{ $paiement->bail->proprietaire->user->nom ?? '' }}<br>
                    Tél : {{ $paiement->bail->proprietaire->user->telephone ?? '' }}<br>
                    Email : {{ $paiement->bail->proprietaire->user->email ?? '' }}
                </div>
            </td>
            <td style="padding-left: 10px;">
                <div class="box">
                    <div class="box-title">Locataire</div>
                    {{ $paiement->locataire->user->prenom ?? '' }} {{ $paiement->locataire->user->nom ?? '' }}<br>
                    Tél : {{ $paiement->locataire->user->telephone ?? '' }}<br>
                    Email : {{ $paiement->locataire->user->email ?? '' }}
                </div>
            </td>
        </tr>
    </table>

    <div class="box">
        <div class="box-title">Détails de la location</div>
        <strong>Logement :</strong> {{ $paiement->bail->logement->numero ?? 'N/A' }} - {{ $paiement->bail->logement->typelogement ?? '' }}<br>
        <strong>Adresse :</strong> {{ $paiement->bail->logement->propriete->adresse ?? 'Adresse non spécifiée' }}<br>
        <strong>Période de location :</strong> {{ \Carbon\Carbon::parse($paiement->date_echeance)->translatedFormat('F Y') }}
    </div>

    <div class="amount">
        Montant total payé : {{ number_format($paiement->montant_paye, 0, ',', ' ') }} FCFA
    </div>

    <p style="margin-top: 20px;">
        Je soussigné(e) {{ $paiement->bail->proprietaire->user->prenom ?? '' }} {{ $paiement->bail->proprietaire->user->nom ?? '' }}, propriétaire du logement désigné ci-dessus, déclare avoir reçu de Monsieur/Madame {{ $paiement->locataire->user->prenom ?? '' }} {{ $paiement->locataire->user->nom ?? '' }}, la somme de <strong>{{ number_format($paiement->montant_paye, 0, ',', ' ') }} FCFA</strong> au titre du loyer et des charges pour la période concernée.
    </p>

    <div class="signature">
        <p>Signature (Bailleur)</p>
        <br><br><br>
        <p>____________________</p>
    </div>

    <div class="footer">
        Document généré automatiquement par la plateforme Luwaas.
    </div>

</body>
</html>
