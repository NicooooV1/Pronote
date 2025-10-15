import { NextResponse } from 'next/server';
import { pronoteAPI } from '@/lib/pronote-api';

export async function POST() {
  try {
    await pronoteAPI.logout();
    return NextResponse.json({ success: true });
  } catch (error) {
    return NextResponse.json({ success: true }); // Toujours succès pour logout
  }
}
