<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property int $id
 * @property int $gym_id
 * @property string $name
 */
class WhatsappTag extends Model
{
    /** @use HasFactory<\Database\Factories\WhatsappTagFactory> */
    use BelongsToGym, HasFactory;

    protected $table = 'wa_tags';

    /**
     * @var list<string>
     */
    protected $fillable = ['gym_id', 'name'];

    /**
     * @return BelongsToMany<WhatsappContact, $this>
     */
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(WhatsappContact::class, 'wa_contact_tag', 'wa_tag_id', 'wa_contact_id');
    }
}
