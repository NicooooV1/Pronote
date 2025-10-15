import { NextResponse } from 'next/server';
import { pronoteAPI, handlePronoteError, type PronoteCredentials } from '@/lib/pronote-api';

export async function POST(request: Request) {
  try {
    const body = await request.json();
    
    const credentials: PronoteCredentials = {
      url: body.url,
      username: body.username,
      password: body.password,
      cas: body.cas
    };

    const user = await pronoteAPI.login(credentials);

    return NextResponse.json({
      success: true,
      user: {
        name: user.name || 'Utilisateur',
        studentClass: user.studentClass?.name || 'Non défini',
        establishment: user.establishment || 'Non défini'
      }
    });
  } catch (error: any) {
    const { message, code } = handlePronoteError(error);
    
    return NextResponse.json(
      { success: false, error: message, code },
      { status: code === 'WRONG_CREDENTIALS' ? 401 : 500 }
    );
  }
}
