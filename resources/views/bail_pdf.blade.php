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

        /* NOUVEAU : Articles Juridiques */
        .legal-section { margin-top: 20px; text-align: justify; }
        .legal-title { font-weight: bold; border-bottom: 1px solid #000; margin-bottom: 10px; padding-bottom: 2px; text-transform: uppercase;}
        .article { margin-bottom: 10px; }
        .article-title { font-weight: bold; text-decoration: underline; display: block; margin-bottom: 3px; }

        /* Signature */
        .signatures { margin-top: 40px; width: 100%; overflow: hidden; page-break-inside: avoid; }
        .sig-box { float: left; width: 45%; }
        .sig-box-right { float: right; width: 45%; }
        .sig-line { margin-top: 50px; border-top: 1px solid #000; width: 100%; }
        
        /* Clearfix pour les floats */
        .clearfix::after { content: ""; clear: both; display: table; }
    </style>
</head>
<body>

    <div class="container">
        <!-- HEADER -->
        <div class="header">
            <h1>Contrat de Bail à Usage d'Habitation</h1>
            <p>Réf: {{ $bail->reference ?? $bail->id }} | Fait le {{ date('d/m/Y') }}</p>
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

        <!-- 2. LE BIEN & CONDITIONS (En une seule ligne de boites si possible, sinon l'un sous l'autre) -->
        <div class="box">
            <div class="box-title">I. DÉSIGNATION ET CONDITIONS FINANCIÈRES</div>
            <table class="info-table">
                <tr>
                    <td class="label">Adresse du bien :</td>
                    <td>{{ $bail->logement->numero ?? '' }} {{ $bail->logement->rue ?? '' }}, {{ $bail->logement->ville }}</td>
                </tr>
                <tr>
                    <td class="label">Durée du bail :</td>
                    <td>Du <strong>{{ \Carbon\Carbon::parse($bail->date_debut)->format('d/m/Y') }}</strong> au <strong>{{ \Carbon\Carbon::parse($bail->date_fin)->format('d/m/Y') }}</strong></td>
                </tr>
                <tr>
                    <td class="label">Loyer Mensuel :</td>
                    <td><strong>{{ number_format($bail->montant, 0, ',', ' ') }} FCFA</strong></td>
                </tr>
                <tr>
                    <td class="label">Garantie locative :</td>
                    <td>{{ number_format($bail->caution, 0, ',', ' ') }} FCFA</td>
                </tr>
            </table>
        </div>

        <!-- 3. ARTICLES JURIDIQUES (AJOUTÉS ICI) -->
        <div class="legal-section">
            <div class="legal-title">II. CONDITIONS GÉNÉRALES</div>

            <div class="article">
                <span class="article-title">Article 1 : Objet et Destination</span>
                Le Bailleur donne en location au Locataire les locaux désignés ci-dessus. Le Locataire accepte les lieux dans l'état où ils se trouvent lors de l'entrée en jouissance. Les locaux sont destinés exclusivement à un usage d'habitation. Le Locataire s'interdit formellement de sous-louer tout ou partie du bien sans l'accord écrit du Bailleur.
            </div>

            <div class="article">
                <span class="article-title">Article 2 : Durée et Renouvellement</span>
                Le présent bail est consenti pour la durée indiquée ci-dessus. À défaut de congé donné par l'une des parties dans les délais légaux avant l'expiration du contrat, celui-ci sera renouvelé par tacite reconduction pour une durée indéterminée ou selon les conditions prévues par la loi en vigueur.
            </div>

            <div class="article">
                <span class="article-title">Article 3 : Loyer et Obligations</span>
                Le loyer est payable d'avance, au plus tard le <strong>{{ $bail->jour_paiement ?? '05' }}</strong> de chaque mois. En outre, le Locataire s'engage à user des lieux en "bon père de famille", à entretenir le bien locatif et à répondre des dégradations survenant pendant la durée du bail. Il s'acquittera également de ses factures de consommation (eau, électricité) durant toute l'occupation.
            </div>
        </div>

        <!-- SIGNATURES -->
        <div class="signatures clearfix">
            <div class="sig-box">
                <div style="font-weight:bold;">LE BAILLEUR</div>
                <div style="font-size:10px; font-style:italic;">(Mention "Lu et approuvé")</div>
                <div class="sig-line"></div>
            </div>
            <div class="sig-box-right">
                <div style="font-weight:bold;">LE LOCATAIRE</div>
                <div style="font-size:10px; font-style:italic;">(Mention "Lu et approuvé")</div>
                <div class="sig-line"></div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px; font-size: 10px; color: #999;">
            Document généré via Luwaas App le {{ date('d/m/Y H:i') }}
        </div>
    </div>

</body>
</html>
