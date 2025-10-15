import { NextResponse } from 'next/server';
import { pronoteAPI, handlePronoteError } from '@/lib/pronote-api';

export async function GET(request: Request) {
  try {
    const { searchParams } = new URL(request.url);
    const fromParam = searchParams.get('from');
    const toParam = searchParams.get('to');

    if (!fromParam || !toParam) {
      return NextResponse.json(
        { success: false, error: 'Paramètres from et to requis' },
        { status: 400 }
      );
    }

    const from = new Date(fromParam);
    const to = new Date(toParam);

    if (isNaN(from.getTime()) || isNaN(to.getTime())) {
      return NextResponse.json(
        { success: false, error: 'Dates invalides' },
        { status: 400 }
      );
    }

    const timetable = await pronoteAPI.getTimetable(from, to);

    return NextResponse.json({
      success: true,
      timetable
    });
  } catch (error: any) {
    const { message, code } = handlePronoteError(error);
    
    return NextResponse.json(
      { success: false, error: message, code },
      { status: code === 'NOT_AUTHENTICATED' ? 401 : 500 }
    );
  }
}
