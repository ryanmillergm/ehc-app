<?php

namespace App\Enums;

enum HomeSectionKey: string
{
    case Hero = 'hero';
    case ImpactStats = 'impact_stats';
    case About = 'about';
    case Pathway = 'pathway';
    case Parallax = 'parallax';
    case Serve = 'serve';
    case ServeSupport = 'serve_support';
    case PreGiveCta = 'pre_give_cta';
    case Give = 'give';
    case Visit = 'visit';
    case FinalCta = 'final_cta';

    public function label(): string
    {
        return match ($this) {
            self::Hero => 'Hero',
            self::ImpactStats => 'Impact Stats',
            self::About => 'About',
            self::Pathway => 'Pathway',
            self::Parallax => 'Parallax',
            self::Serve => 'Serve',
            self::ServeSupport => 'Serve Support',
            self::PreGiveCta => 'Pre-Give CTA',
            self::Give => 'Give',
            self::Visit => 'Visit',
            self::FinalCta => 'Final CTA',
        };
    }
}
