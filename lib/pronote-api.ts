import pronote from 'pronote-api';

// Types stricts
export interface PronoteCredentials {
  url: string;
  username: string;
  password: string;
  cas?: string;
}

export interface PronoteSession {
  user: any;
  session: any;
  lastActivity: number;
}

export interface CachedData<T> {
  data: T;
  timestamp: number;
  ttl: number; // Time to live en millisecondes
}

export class PronoteAPIError extends Error {
  constructor(
    message: string,
    public code: string,
    public originalError?: any
  ) {
    super(message);
    this.name = 'PronoteAPIError';
  }
}

// Singleton pour gérer la session Pronote
class PronoteAPI {
  private static instance: PronoteAPI;
  private session: PronoteSession | null = null;
  private cache: Map<string, CachedData<any>> = new Map();
  private readonly SESSION_TIMEOUT = 30 * 60 * 1000; // 30 minutes
  private readonly DEFAULT_CACHE_TTL = 5 * 60 * 1000; // 5 minutes

  private constructor() {}

  public static getInstance(): PronoteAPI {
    if (!PronoteAPI.instance) {
      PronoteAPI.instance = new PronoteAPI();
    }
    return PronoteAPI.instance;
  }

  // Validation des credentials
  private validateCredentials(credentials: PronoteCredentials): void {
    if (!credentials.url || typeof credentials.url !== 'string') {
      throw new PronoteAPIError('URL invalide', 'INVALID_URL');
    }
    if (!credentials.username || typeof credentials.username !== 'string') {
      throw new PronoteAPIError('Nom d\'utilisateur invalide', 'INVALID_USERNAME');
    }
    if (!credentials.password || typeof credentials.password !== 'string') {
      throw new PronoteAPIError('Mot de passe invalide', 'INVALID_PASSWORD');
    }
  }

  // Vérifier si la session est toujours valide
  private isSessionValid(): boolean {
    if (!this.session) return false;
    const now = Date.now();
    return (now - this.session.lastActivity) < this.SESSION_TIMEOUT;
  }

  // Mettre à jour l'activité de la session
  private updateSessionActivity(): void {
    if (this.session) {
      this.session.lastActivity = Date.now();
    }
  }

  // Gestion du cache
  private getCachedData<T>(key: string): T | null {
    const cached = this.cache.get(key);
    if (!cached) return null;

    const now = Date.now();
    if ((now - cached.timestamp) > cached.ttl) {
      this.cache.delete(key);
      return null;
    }

    return cached.data as T;
  }

  private setCachedData<T>(key: string, data: T, ttl: number = this.DEFAULT_CACHE_TTL): void {
    this.cache.set(key, {
      data,
      timestamp: Date.now(),
      ttl
    });
  }

  private clearCache(): void {
    this.cache.clear();
  }

  // Connexion à Pronote
  public async login(credentials: PronoteCredentials): Promise<any> {
    try {
      this.validateCredentials(credentials);

      // Si une session valide existe déjà, la retourner
      if (this.isSessionValid()) {
        this.updateSessionActivity();
        return this.session!.user;
      }

      // Nouvelle connexion
      const session = await pronote(
        credentials.url,
        credentials.username,
        credentials.password,
        credentials.cas
      );

      if (!session || !session.user) {
        throw new PronoteAPIError('Échec de connexion', 'LOGIN_FAILED');
      }

      this.session = {
        user: session.user,
        session: session,
        lastActivity: Date.now()
      };

      this.clearCache(); // Nettoyer le cache lors d'une nouvelle connexion

      return session.user;
    } catch (error: any) {
      this.session = null;
      
      if (error instanceof PronoteAPIError) {
        throw error;
      }

      if (error.message?.includes('Wrong credentials')) {
        throw new PronoteAPIError('Identifiants incorrects', 'WRONG_CREDENTIALS', error);
      }

      if (error.message?.includes('network') || error.code === 'ENOTFOUND') {
        throw new PronoteAPIError('Erreur de connexion réseau', 'NETWORK_ERROR', error);
      }

      throw new PronoteAPIError('Erreur de connexion inconnue', 'UNKNOWN_ERROR', error);
    }
  }

  // Déconnexion
  public async logout(): Promise<void> {
    try {
      if (this.session?.session?.logout) {
        await this.session.session.logout();
      }
    } catch (error) {
      console.error('Erreur lors de la déconnexion:', error);
    } finally {
      this.session = null;
      this.clearCache();
    }
  }

  // Vérifier si connecté
  public isAuthenticated(): boolean {
    return this.isSessionValid();
  }

  // Obtenir l'utilisateur actuel
  public getCurrentUser(): any {
    if (!this.isSessionValid()) {
      throw new PronoteAPIError('Session expirée', 'SESSION_EXPIRED');
    }
    this.updateSessionActivity();
    return this.session!.user;
  }

  // Récupérer les devoirs avec cache
  public async getHomework(from?: Date, to?: Date): Promise<any[]> {
    if (!this.isSessionValid()) {
      throw new PronoteAPIError('Non authentifié', 'NOT_AUTHENTICATED');
    }

    const cacheKey = `homework_${from?.getTime() || 'all'}_${to?.getTime() || 'all'}`;
    const cached = this.getCachedData<any[]>(cacheKey);
    
    if (cached) {
      return cached;
    }

    try {
      this.updateSessionActivity();
      const user = this.session!.user;
      
      const homework = from && to 
        ? await user.homeworks(from, to)
        : await user.homeworks();

      const validHomework = Array.isArray(homework) ? homework : [];
      this.setCachedData(cacheKey, validHomework);
      
      return validHomework;
    } catch (error: any) {
      throw new PronoteAPIError('Erreur lors de la récupération des devoirs', 'HOMEWORK_ERROR', error);
    }
  }

  // Récupérer l'emploi du temps avec cache
  public async getTimetable(from: Date, to: Date): Promise<any[]> {
    if (!this.isSessionValid()) {
      throw new PronoteAPIError('Non authentifié', 'NOT_AUTHENTICATED');
    }

    const cacheKey = `timetable_${from.getTime()}_${to.getTime()}`;
    const cached = this.getCachedData<any[]>(cacheKey);
    
    if (cached) {
      return cached;
    }

    try {
      this.updateSessionActivity();
      const user = this.session!.user;
      
      const timetable = await user.timetable(from, to);
      const validTimetable = Array.isArray(timetable) ? timetable : [];
      
      this.setCachedData(cacheKey, validTimetable);
      
      return validTimetable;
    } catch (error: any) {
      throw new PronoteAPIError('Erreur lors de la récupération de l\'emploi du temps', 'TIMETABLE_ERROR', error);
    }
  }

  // Récupérer les notes avec cache
  public async getGrades(period?: string): Promise<any> {
    if (!this.isSessionValid()) {
      throw new PronoteAPIError('Non authentifié', 'NOT_AUTHENTICATED');
    }

    const cacheKey = `grades_${period || 'all'}`;
    const cached = this.getCachedData<any>(cacheKey);
    
    if (cached) {
      return cached;
    }

    try {
      this.updateSessionActivity();
      const user = this.session!.user;
      
      const grades = period 
        ? await user.grades(period)
        : await user.grades();

      this.setCachedData(cacheKey, grades);
      
      return grades;
    } catch (error: any) {
      throw new PronoteAPIError('Erreur lors de la récupération des notes', 'GRADES_ERROR', error);
    }
  }

  // Récupérer les absences avec cache
  public async getAbsences(): Promise<any> {
    if (!this.isSessionValid()) {
      throw new PronoteAPIError('Non authentifié', 'NOT_AUTHENTICATED');
    }

    const cacheKey = 'absences';
    const cached = this.getCachedData<any>(cacheKey);
    
    if (cached) {
      return cached;
    }

    try {
      this.updateSessionActivity();
      const user = this.session!.user;
      
      const absences = await user.absences();
      this.setCachedData(cacheKey, absences);
      
      return absences;
    } catch (error: any) {
      throw new PronoteAPIError('Erreur lors de la récupération des absences', 'ABSENCES_ERROR', error);
    }
  }

  // Récupérer les évaluations avec cache
  public async getEvaluations(): Promise<any> {
    if (!this.isSessionValid()) {
      throw new PronoteAPIError('Non authentifié', 'NOT_AUTHENTICATED');
    }

    const cacheKey = 'evaluations';
    const cached = this.getCachedData<any>(cacheKey);
    
    if (cached) {
      return cached;
    }

    try {
      this.updateSessionActivity();
      const user = this.session!.user;
      
      const evaluations = await user.evaluations();
      this.setCachedData(cacheKey, evaluations);
      
      return evaluations;
    } catch (error: any) {
      throw new PronoteAPIError('Erreur lors de la récupération des évaluations', 'EVALUATIONS_ERROR', error);
    }
  }

  // Récupérer les menus avec cache
  public async getMenus(from: Date, to: Date): Promise<any[]> {
    if (!this.isSessionValid()) {
      throw new PronoteAPIError('Non authentifié', 'NOT_AUTHENTICATED');
    }

    const cacheKey = `menus_${from.getTime()}_${to.getTime()}`;
    const cached = this.getCachedData<any[]>(cacheKey);
    
    if (cached) {
      return cached;
    }

    try {
      this.updateSessionActivity();
      const user = this.session!.user;
      
      const menus = await user.menus(from, to);
      const validMenus = Array.isArray(menus) ? menus : [];
      
      this.setCachedData(cacheKey, validMenus);
      
      return validMenus;
    } catch (error: any) {
      throw new PronoteAPIError('Erreur lors de la récupération des menus', 'MENUS_ERROR', error);
    }
  }

  // Invalider le cache (utile pour forcer un refresh)
  public invalidateCache(key?: string): void {
    if (key) {
      this.cache.delete(key);
    } else {
      this.clearCache();
    }
  }
}

// Export de l'instance singleton
export const pronoteAPI = PronoteAPI.getInstance();

// Helper pour gérer les erreurs de manière uniforme
export function handlePronoteError(error: any): { message: string; code: string } {
  if (error instanceof PronoteAPIError) {
    return {
      message: error.message,
      code: error.code
    };
  }

  return {
    message: 'Une erreur inattendue s\'est produite',
    code: 'UNKNOWN_ERROR'
  };
}
