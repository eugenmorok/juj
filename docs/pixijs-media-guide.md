# PixiJS media guide

## Runtime formats

- Arena backgrounds: WebP, 1920 x 1080 or wider.
- Creature portraits: WebP or PNG, 512 x 512.
- Static battle creatures: transparent WebP or PNG, up to 2048 x 2048.
- Frame animation: transparent WebP spritesheet plus PixiJS-compatible JSON.
- Item effects: transparent WebP or PNG.
- Sound: OGG as the primary format, MP3 as a compatibility fallback.

The first release animates static creature images in PixiJS. This keeps asset
production inexpensive while still providing movement, hits, misses, critical
flashes, healing effects, floating damage values, and defeat animations.

## Asset sources

Use only assets that the project is allowed to redistribute:

1. Original AI-generated images followed by manual art review.
2. Commissioned work with written commercial and redistribution rights.
3. Licensed packs from marketplaces such as itch.io, GameDev Market, or
   OpenGameArt, after recording the exact license and author.
4. In-house drawings and animations.

Do not copy sprites, frames, interface elements, or textures from existing
commercial games. References may guide composition and mood, but shipped assets
must be original or explicitly licensed.

## Administration

The Filament panels accept:

- Species portrait, battle image, spritesheet image, and spritesheet JSON.
- Arena background image.
- Item effect image and sound.

Uploaded files are stored on the Laravel `public` disk under `storage/app/public`.
The deploy process must run `php artisan storage:link`.

Bundled fallback assets live in `public/game-assets` and are versioned in Git.
