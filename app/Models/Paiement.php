<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Paiement extends Model
{
    protected $fillable = [
        'locataire_id',
        'bail_id',
        'type',
        'montant_attendu',
        'montant_paye',
        'montant_restant',
        'statut',
        'date_echeance',
        'date_paiement',
        'periode',
        'verification_token',
    ];

    /**
     * Génère automatiquement un token de vérification unique à la création.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::creating(function (self $paiement) {
            if (empty($paiement->verification_token)) {
                $paiement->verification_token = Str::random(48);
            }
        });
    }

    public function bail()
    {
        return $this->belongsTo(Bail::class);
    }

    public function locataire()
    {
        return $this->belongsTo(Locataire::class);
    }

    public function transactions()
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Détermine si le paiement est certifié Luwaas (payé en ligne, via un opérateur valide).
     * Les paiements manuels (especes, wave_direct, virement, cheque) ne sont pas certifiés.
     */
    public function getEstCertifieAttribute(): bool
    {
        // On considère un paiement certifié s'il a au moins une transaction VALIDÉE
        // qui N'EST PAS un mode manuel
        return $this->transactions()
            ->where('statut', 'valide')
            ->whereNotIn('mode_paiement', ['especes', 'wave_direct', 'virement', 'cheque'])
            ->exists();
    }

    /**
     * URL publique de vérification de la quittance (utilisée dans le QR code).
     */
    public function getVerificationUrlAttribute(): ?string
    {
        if (!$this->verification_token) {
            return null;
        }
        return url('/verifier/paiement/' . $this->verification_token);
    }
}
