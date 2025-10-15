import { NextResponse } from 'next/server';
import { pronoteAPI, handlePronoteError } from '@/lib/pronote-api';

export async function GET(request: Request) {
  try {
    const { searchParams } = new URL(request.url);
    const period = searchParams.get('period') || undefined;

    const grades = await pronoteAPI.getGrades(period);

    return NextResponse.json({
      success: true,
      grades
    });
  } catch (error: any) {
    const { message, code } = handlePronoteError(error);
    
    return NextResponse.json(
      { success: false, error: message, code },
      { status: code === 'NOT_AUTHENTICATED' ? 401 : 500 }
    );
  }
}
