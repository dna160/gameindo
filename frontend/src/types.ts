// Frontend types — mirrors shared/types.ts for the browser bundle

export type Pillar =
  | 'home'
  | 'esports'
  | 'streamer'
  | 'tech'
  | 'entertainment';

export const PILLARS: Pillar[] = ['home', 'esports', 'streamer', 'tech', 'entertainment'];

export const PILLAR_LABELS: Record<Pillar, string> = {
  home:          'Video Game',
  esports:       'Esports',
  streamer:      'Streamer',
  tech:          'Tech',
  entertainment: 'Entertainment',
};

export const PILLAR_COLORS: Record<Pillar, string> = {
  home:          '#22d3ee',
  esports:       '#f43f5e',
  streamer:      '#a855f7',
  tech:          '#38bdf8',
  entertainment: '#f59e0b',
};

export type ArticleStatus =
  | 'PROCESSING'
  | 'GREEN'
  | 'YELLOW'
  | 'RED'
  | 'FAILED'
  | 'PUBLISHED';

export interface ArticleImage {
  url: string;
  alt: string;
  isFeatured: boolean;
  sourceQuery?: string;
}

export interface Article {
  id: string;
  title: string;
  pillar: Pillar;
  sourceUrl: string;
  status: ArticleStatus;
  revisionCount: number;
  content: string | null;
  contentHtml: string | null;
  images: ArticleImage[] | null;
  editorNotes: string | null;
  wpPostId: number | null;
  wpPostUrl: string | null;
  createdAt: string;
  updatedAt: string;
}

export interface PipelineLogEntry {
  timestamp: string;
  level: 'info' | 'warn' | 'error';
  message: string;
  agent?: string;
  articleId?: string;
}

export interface PipelineRun {
  id: string;
  status: 'RUNNING' | 'COMPLETED' | 'FAILED';
  articlesProcessed: number;
  startedAt: string;
  completedAt: string | null;
  logs: PipelineLogEntry[] | null;
}

export interface PipelineStatusData {
  isRunning: boolean;
  currentRun: PipelineRun | null;
  lastRun: PipelineRun | null;
}

export interface DashboardStats {
  total: number;
  green: number;
  yellow: number;
  red: number;
  processing: number;
  published: number;
  failed: number;
  byPillar: Record<Pillar, number>;
}

export interface ApiResponse<T> {
  success: boolean;
  data?: T;
  error?: string;
}
