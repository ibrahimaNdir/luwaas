<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de Location #{{ $bail->id }}</title>
    <style>
        body { font-family: 'Helvetica', sans-serif; font-size: 12px; line-height: 1.4; color: #000; }
        .container { margin: 0 auto; padding: 10px 30px; }
        
        /* En-tête */
        .header { text-align: center; border-bottom: 2px solid #000; padding-bottom: 10px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 18px; text-transform: uppercase; }
        .header p { margin: 5px 0 0; font-size: 11px; color: #555; }

        /* Sections Boites */
        .box { border: 1px solid #999; padding: 10px; margin-bottom: 15px; background-color: #fff; }
        .box-title { font-weight: bold; text-decoration: underline; margin-bottom: 5px; font-size: 13px; }

        /* Grille Parties */
        .parties-container { width: 100%; margin-bottom: 15px; overflow: hidden; }
        .party-box { float: left; width: 48%; border: 1px solid #999; padding: 10px; height: 90px; }
        .party-right { float: right; }

        /* Tables */
        .info-table { width: 100%; }
        .info-table td { padding: 2px 0; vertical-align: top; }
        .label { font-weight: bold; width: 130px; }

        /* Articles Juridiques */
        .legal-section { margin-top: 20px; text-align: justify; }
        .legal-title { font-weight: bold; border-bottom: 1px solid #000; margin-bottom: 10px; padding-bottom: 2px; text-transform: uppercase;}
        .article { margin-bottom: 10px; }
        .article-title { font-weight: bold; text-decoration: underline; display: block; margin-bottom: 3px; }

        /* ✅ NOUVEAU : Cartouche de certification */
        .certification-box {
            border: 3px solid #28a745;
            background-color: #f0fff4;
            padding: 15px;
            margin-top: 30px;
            page-break-inside: avoid;
        }
        .cert-title {
            font-size: 14px;
            font-weight: bold;
            text-align: center;
            color: #28a745;
            margin-bottom: 10px;
            text-transform: uppercase;
        }
        .cert-details {
            background-color: #fff;
            border: 1px solid #ccc;
            padding: 10px;
            margin: 10px 0;
            font-size: 11px;
            line-height: 1.6;
        }
        
        /* Clearfix pour les floats */
        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>

    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1>Contrat de Bail à Usage d'Habitation</h1>
            <p>Réf: {{ $bail->id }} | Signé électroniquement le {{ $bail->date_activation->format('d/m/Y à H:i') }}</p>
        </div>

        <!-- 1. LES PARTIES -->
        <div class="parties-container clearfix">
            <div class="party-box">
                <div class="box-title">LE BAILLEUR</div>
                <strong>{{ $bail->logement->propriete->proprietaire->user->prenom }} {{ $bail->logement->propriete->proprietaire->user->nom }}</strong><br>
                Tel: {{ $bail->logement->propriete->proprietaire->user->telephone ?? '-' }}<br>
                Email: {{ $bail->logement->propriete->proprietaire->user->email ?? '-' }}
            </div>
            <div class="party-box party-right">
                <div class="box-title">LE LOCATAIRE</div>
                <strong>{{ $bail->locataire->user->prenom }} {{ $bail->locataire->user->nom }}</strong><br>
                Tel: {{ $bail->locataire->user->telephone ?? '-' }}<br>
                Email: {{ $bail->locataire->user->email ?? '-' }}
            </div>
        </div>

        <!-- 2. LE BIEN & CONDITIONS -->
        <div class="box">
            <div class="box-title">I. DÉSIGNATION ET CONDITIONS FINANCIÈRES</div>
            <table class="info-table">
                <tr>
                    <td class="label">Le Logement :</td>
                    <td>{{ $bail->logement->numero ?? '' }}, {{ $bail->logement->propriete->adresse ?? '' }}</td>
                </tr> 
                <tr>
                    <td class="label">Type :</td>
                    <td>{{ $bail->logement->typelogement ?? '' }}</td>
                </tr>
                <tr>
                    <td class="label">Durée du bail :</td>
                    <td>Du <strong>{{ \Carbon\Carbon::parse($bail->date_debut)->format('d/m/Y') }}</strong> au <strong>{{ \Carbon\Carbon::parse($bail->date_fin)->format('d/m/Y') }}</strong></td>
                </tr>
                <tr>
                    <td class="label">Loyer Mensuel :</td>
                    <td><strong>{{ number_format($bail->montant_loyer, 0, ',', ' ') }} FCFA</strong></td>
                </tr>
                <tr>
                    <td class="label">Charges mensuelles :</td>
                    <td>{{ number_format($bail->charges_mensuelles, 0, ',', ' ') }} FCFA</td>
                </tr>
                <tr>
                    <td class="label">Garantie locative :</td>
                    <td>{{ number_format($bail->montant_caution_total, 0, ',', ' ') }} FCFA ({{ $bail->nombre_mois_caution }} mois de loyer)</td>
                </tr>
                <tr>
                    <td class="label">Jour d'échéance :</td>
                    <td>Le {{ $bail->jour_echeance }} de chaque mois</td>
                </tr>
            </table>
        </div>

        <!-- 3. ARTICLES JURIDIQUES -->
        <div class="legal-section">
            <div class="legal-title">II. CONDITIONS GÉNÉRALES</div>

            <div class="article">
                <span class="article-title">Article 1 : Objet et Destination</span>
                Le Bailleur donne en location au Locataire les locaux désignés ci-dessus. Le Locataire accepte les lieux dans l'état où ils se trouvent lors de l'entrée en jouissance. Les locaux sont destinés exclusivement à un usage d'habitation. Le Locataire s'interdit formellement de sous-louer tout ou partie du bien sans l'accord écrit du Bailleur.
            </div>

            <div class="article">
                <span class="article-title">Article 2 : Durée et Renouvellement</span>
                Le présent bail est consenti pour la durée indiquée ci-dessus. 
                @if($bail->renouvellement_automatique)
                Le bail sera renouvelé automatiquement par tacite reconduction sauf dénonciation par l'une des parties dans les délais légaux.
                @else
                Le bail prendra fin à la date indiquée sauf accord des deux parties pour un renouvellement.
                @endif
            </div>

            <div class="article">
                <span class="article-title">Article 3 : Loyer et Modalités de Paiement</span>
                Le loyer est payable d'avance, au plus tard le <strong>{{ $bail->jour_echeance }}</strong> de chaque mois. Tout retard de paiement pourra entraîner l'application de pénalités conformément à la réglementation en vigueur.
            </div>

            <div class="article">
                <span class="article-title">Article 4 : Obligations du Locataire</span>
                Le Locataire s'engage à user des lieux en "bon père de famille", à entretenir le bien locatif et à répondre des dégradations survenant pendant la durée du bail. Il s'acquittera également de ses factures de consommation (eau, électricité) durant toute l'occupation.
            </div>

            @if($bail->conditions_speciales)
            <div class="article">
                <span class="article-title">Article 5 : Conditions Particulières</span>
                {{ $bail->conditions_speciales }}
            </div>
            @endif
        </div>

        <!-- ✅ CARTOUCHE DE CERTIFICATION (NOUVEAU) -->
        <div class="certification-box">
            <div class="cert-title">🔒 CERTIFICATION DE SIGNATURE ÉLECTRONIQUE</div>
            
            <p style="text-align: justify; margin-bottom: 10px;">
                <strong>Le présent contrat a été signé électroniquement</strong> par le paiement de la garantie locative et du premier mois de loyer, 
                conformément à la <strong>Loi n°2008-08 du 25 janvier 2008</strong> sur les transactions électroniques au Sénégal.
            </p>
            
            <div class="cert-details">
                <strong>Signé par :</strong> {{ $bail->locataire->user->prenom }} {{ $bail->locataire->user->nom }}<br>
                <strong>Mode de signature :</strong> Paiement Mobile Money<br>
                <strong>Date et heure de signature :</strong> {{ $bail->date_activation->format('d/m/Y à H:i:s') }}<br>
                <strong>Montant payé :</strong> {{ number_format($bail->montant_caution_total + $bail->montant_loyer, 0, ',', ' ') }} FCFA<br>
                <strong>Statut :</strong> ✅ Transaction validée et confirmée
            </div>
            
            <p style="font-style: italic; font-size: 10px; text-align: justify; margin-top: 10px; color: #555;">
                Conformément à l'article 16 de la loi n°2008-08, la signature électronique réalisée par paiement Mobile Money 
                a la même valeur juridique qu'une signature manuscrite. Les deux parties reconnaissent avoir lu, compris et accepté 
                l'ensemble des clauses du présent contrat.
            </p>
        </div>
        
        <div style="text-align: center; margin-top: 20px; font-size: 10px; color: #999;">
            Document généré et certifié via Luwaas App le {{ now()->format('d/m/Y à H:i:s') }}
        </div>
    </div>

</body>
</html>