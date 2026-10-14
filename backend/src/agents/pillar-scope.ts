/**
 * GameIndo pillar definitions, shared by both Scouts.
 *
 * One source of truth so the main triage and the Underquota "sniper" triage
 * cannot drift apart -- they had duplicate, subtly different pillar wording
 * before, and the underquota copy was the one that let anime through as games.
 *
 * Every pillar carries explicit exclusions. The brief is not just "sort items
 * into five buckets" but "keep each pillar free of the other four": a figure
 * of a game character is Entertainment, not Video Game; a gaming laptop review
 * is Tech, not Video Game; a VTuber playing a game is Streamer, not Video Game.
 */
export const PILLAR_DEFINITIONS = `   - **Video Game** — games on **Nintendo Switch (incl. Switch 2),
     PlayStation 5, PC (Steam/Epic/GOG/Windows), or Xbox**: announcements,
     release dates, trailers, updates, DLC, reviews, ports, remasters; and the
     industry around those platforms — expos (gamescom, Tokyo Game Show,
     Nintendo Direct, State of Play), publisher/studio news, developer
     interviews, awards.
     NOT Video Game: mobile-only / gacha / arcade-only / browser-only titles;
     legacy-only hardware (PS4, 3DS, Wii U, Vita); a figure, plush, soundtrack
     or manga OF a game (that is Entertainment); a gaming laptop, GPU or
     headset (that is Tech); a streamer playing a game (that is Streamer);
     a tournament or roster move (that is Esports).

   - **Esports** — competitive play: tournaments, leagues and circuits (MPL,
     LCK, LEC, VCT, The International, Worlds, ESL, gamescom's competitive
     programme), match results, roster and coaching moves, team and org
     business, prize pools, player transfers, qualifiers.
     NOT Esports: an ordinary game release or patch with no competitive angle
     (that is Video Game); a streamer's personal drama (that is Streamer).

   - **Streamer** — creators and their platforms: VTubers (hololive, Nijisanji,
     Cover, ANYCOLOR), Twitch / YouTube / Kick streamers, debuts, graduations,
     model or costume reveals, collabs, subscriber milestones, bans and
     platform policy, creator merch and concerts.
     NOT Streamer: a game a creator happens to be playing, unless the story is
     about the creator; an esports pro's match performance (that is Esports).

   - **Tech** — consumer technology and hardware: PCs, GPUs, CPUs, phones,
     tablets, audio gear, wearables, TVs and monitors, AI products and
     services, apps and software, telecoms, EVs and gadgets. Gaming hardware
     (consoles as devices, gaming laptops, controllers, headsets) belongs here.
     NOT Tech: a game running on that hardware (that is Video Game).

   - **Entertainment** — anime, manga, light novels, figures and collectibles,
     music and idols, live-action film and TV, celebrity and industry news.
     Merchandise of a game franchise belongs here, not in Video Game.
     NOT Entertainment: anything whose subject is a playable game, a
     competition, a creator, or a device.`;

/** Pillars whose label the model may return. Keep in sync with PILLAR_LABELS. */
export const PILLAR_LABEL_LIST = 'Video Game, Esports, Streamer, Tech, Entertainment';
