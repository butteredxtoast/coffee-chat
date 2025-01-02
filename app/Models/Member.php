<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Member extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'pronouns',
        'city',
        'preferred_contact_method',
        'slack_handle',
        'phone',
        'email',
        'anniversary_year',
        'notes',
    ];

    protected $casts = [
        'anniversary_year' => 'integer',
    ];

    // Getters
    public function getName(): string
    {
        return $this->name;
    }

    public function getPronouns(): ?string
    {
        return $this->pronouns;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function getPreferredContactMethod(): ?string
    {
        return $this->preferred_contact_method;
    }

    public function getSlackHandle(): ?string
    {
        return $this->slack_handle;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getAnniversaryYear(): ?int
    {
        return $this->anniversary_year;
    }

    public function getNotes(): ?string
    {
        return $this->notes;
    }

    // Setters
    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function setPronouns(?string $pronouns): self
    {
        $this->pronouns = $pronouns;
        return $this;
    }

    public function setCity(?string $city): self
    {
        $this->city = $city;
        return $this;
    }

    public function setPreferredContactMethod(?string $preferred_contact_method): self
    {
        $this->preferred_contact_method = $preferred_contact_method;
        return $this;
    }

    public function setSlackHandle(?string $slack_handle): self
    {
        $this->slack_handle = $slack_handle;
        return $this;
    }

    public function setPhone(?string $phone): self
    {
        $this->phone = $phone;
        return $this;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;
        return $this;
    }

    public function setAnniversaryYear(?int $anniversary_year): self
    {
        $this->anniversary_year = $anniversary_year;
        return $this;
    }

    public function setNotes(?string $notes): self
    {
        $this->notes = $notes;
        return $this;
    }

    // Relationships
    public function currentMatch(): HasOne

    {
        return $this->hasOne(Matches::class, 'member1_id')
            ->where('is_current', true)
            ->orWhere(function ($query) {
                $query->where('member2_id', $this->id)
                    ->where('is_current', true);
            });
    }

    public function pastMatches(): HasMany
    {
        return $this->hasMany(Matches::class, 'member1_id')
            ->where('is_current', false)
            ->orWhere(function ($query) {
                $query->where('member2_id', $this->id)
                    ->where('is_current', false);
            });
    }

    public function hasMetWith(Member $otherMember): bool
    {
        return Matches::where(function ($query) use ($otherMember) {
            $query->where('member1_id', $this->id)
                ->where('member2_id', $otherMember->id)
                ->where('met', true);
        })->orWhere(function ($query) use ($otherMember) {
            $query->where('member1_id', $otherMember->id)
                ->where('member2_id', $this->id)
                ->where('met', true);
        })->exists();
    }
}
