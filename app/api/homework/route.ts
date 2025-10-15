import { NextResponse } from 'next/server';
import { pronoteAPI, handlePronoteError } from '@/lib/pronote-api';

export async function GET(request: Request) {
  try {
    const { searchParams } = new URL(request.url);
    const fromParam = searchParams.get('from');
    const toParam = searchParams.get('to');

    const from = fromParam ? new Date(fromParam) : undefined;
    const to = toParam ? new Date(toParam) : undefined;

    const homework = await pronoteAPI.getHomework(from, to);

    return NextResponse.json({
      success: true,
      homework
    });
  } catch (error: any) {
    const { message, code } = handlePronoteError(error);
    
    return NextResponse.json(
      { success: false, error: message, code },
      { status: code === 'NOT_AUTHENTICATED' ? 401 : 500 }
    );
  }
}
