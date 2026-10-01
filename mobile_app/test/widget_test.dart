import 'package:flutter_test/flutter_test.dart';
import 'package:padanam_lms/core/utils/format.dart';
import 'package:padanam_lms/core/utils/json.dart';

void main() {
  test('json helpers never crash on bad data', () {
    final Json j = {'a': '12', 'b': 3.7, 'c': null, 'd': [1, 2], 'e': {'x': 1}};
    expect(j.i('a'), 12);
    expect(j.i('b'), 3);
    expect(j.s('c', 'none'), 'none');
    expect(j.ln('d'), [1.0, 2.0]);
    expect(j.m('e').i('x'), 1);
    expect(j.l('missing'), isEmpty);
  });

  test('formatting', () {
    expect(groupIndian(120000), '1,20,000');
    expect(mmss(905), '15:05');
    expect(num1(29.5), '29.5');
    expect(tr({'en': 'Hi', 'ml': 'ഹായ്'}, 'ml'), 'ഹായ്');
    expect(tr({'en': 'Hi'}, 'hi'), 'Hi');
  });
}
